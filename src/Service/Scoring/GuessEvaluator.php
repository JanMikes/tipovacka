<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Entity\CompetitionRuleConfiguration;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\SportMatch;
use App\Repository\CompetitionRuleConfigurationRepository;
use App\Repository\MatchEventRepository;
use App\Rule\RuleRegistry;
use App\Service\Identity\ProvideIdentity;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Evaluates guesses against finished matches using the guess's COMPETITION rule
 * configuration — the same match may yield different points in different competitions.
 *
 * Config resolution merges stored rows with registry defaults: a stored row always
 * wins (enabled + points); a registered rule with no stored row participates iff
 * `enabledByDefault`, using `defaultPoints`. This keeps the evaluator in exact
 * agreement with the GetCompetitionRuleConfiguration display query — nothing is
 * silently skipped or provisioned.
 *
 * Points model: `Rule::evaluate()` returns a multiplier ≥ 0 (binary rules 0/1,
 * counting rules the hit count); the evaluator stores `multiplier × configured
 * points` as the rule-points row. Multiplier 0 stores no row (only hits are stored).
 *
 * Caching choice: the evaluator caches BOTH the per-competition config map and
 * the per-match {@see MatchContext} (goal scorer ids for the scorer rule)
 * internally, keyed by UUID. SportMatchFinishedHandler / SportMatchScoreUpdatedHandler
 * evaluate many guesses across multiple competitions for ONE match — each
 * competition's config and the match's events load exactly once, not per guess.
 * Caches drop on kernel/worker reset ({@see ResetInterface}) and explicitly via
 * {@see forgetCompetition} (recalculation after config change) /
 * {@see forgetMatch} (re-evaluation after a score correction replaced the events).
 */
class GuessEvaluator implements ResetInterface
{
    /** @var array<string, array<string, CompetitionRuleConfiguration>> competition UUID → stored rows by rule identifier */
    private array $configCache = [];

    /** @var array<string, MatchContext> sport match UUID → per-match rule context */
    private array $contextCache = [];

    public function __construct(
        private readonly RuleRegistry $ruleRegistry,
        private readonly CompetitionRuleConfigurationRepository $configurationRepository,
        private readonly MatchEventRepository $matchEventRepository,
        private readonly ProvideIdentity $identity,
    ) {
    }

    /**
     * Evaluates a guess against a finished match and returns a new GuessEvaluation.
     *
     * Caller is responsible for persisting the returned evaluation.
     * Returns null if the match is not finished or has no scores (defensive).
     */
    public function evaluate(Guess $guess, SportMatch $match, \DateTimeImmutable $now): ?GuessEvaluation
    {
        if (!$match->isFinished || null === $match->homeScore || null === $match->awayScore) {
            return null;
        }

        $storedConfigurations = $this->configurationsFor($guess->competition->id);
        $context = $this->contextFor($match->id);

        $evaluation = new GuessEvaluation(
            id: $this->identity->next(),
            guess: $guess,
            evaluatedAt: $now,
        );

        foreach ($this->ruleRegistry->all() as $ruleIdentifier => $rule) {
            $configuration = $storedConfigurations[$ruleIdentifier] ?? null;

            $enabled = null === $configuration ? $rule->enabledByDefault : $configuration->enabled;

            if (!$enabled) {
                continue;
            }

            $multiplier = $rule->evaluate($guess, $match, $context);

            if ($multiplier < 1) {
                continue;
            }

            $points = null === $configuration ? $rule->defaultPoints : $configuration->points;

            $evaluation->addRulePoints(new GuessEvaluationRulePoints(
                id: $this->identity->next(),
                evaluation: $evaluation,
                ruleIdentifier: $ruleIdentifier,
                points: $multiplier * $points,
            ));
        }

        return $evaluation;
    }

    public function forgetCompetition(Uuid $competitionId): void
    {
        unset($this->configCache[$competitionId->toRfc4122()]);
    }

    /**
     * Drops the cached per-match context — called by the score-correction path
     * (SportMatchScoreUpdatedHandler) after the event sheet was replaced, so a
     * re-evaluation within the same process never sees stale scorer ids.
     */
    public function forgetMatch(Uuid $sportMatchId): void
    {
        unset($this->contextCache[$sportMatchId->toRfc4122()]);
    }

    /**
     * Kernel/worker reset (autoconfigured via {@see ResetInterface}) — drops the
     * caches between requests/messages/tests so stale rows never leak.
     */
    public function reset(): void
    {
        $this->configCache = [];
        $this->contextCache = [];
    }

    /**
     * @return array<string, CompetitionRuleConfiguration>
     */
    private function configurationsFor(Uuid $competitionId): array
    {
        $key = $competitionId->toRfc4122();

        return $this->configCache[$key] ??= $this->configurationRepository->mapForCompetition($competitionId);
    }

    private function contextFor(Uuid $sportMatchId): MatchContext
    {
        $key = $sportMatchId->toRfc4122();

        return $this->contextCache[$key] ??= new MatchContext(
            goalScorerPlayerIds: $this->matchEventRepository->goalScorerPlayerIds($sportMatchId),
        );
    }
}
