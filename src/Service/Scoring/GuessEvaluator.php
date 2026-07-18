<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Entity\CompetitionRuleConfiguration;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\SportMatch;
use App\Repository\CompetitionRuleConfigurationRepository;
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
 * Caching choice: the evaluator caches the per-competition config map internally
 * (keyed by competition id) rather than making handlers thread a map through the
 * API. SportMatchFinishedHandler / SportMatchScoreUpdatedHandler evaluate many
 * guesses across multiple competitions for one match and hit each competition's
 * config exactly once. The cache is dropped on kernel/worker reset
 * ({@see ResetInterface}) and explicitly by the recalculation handler via
 * {@see forgetCompetition} so a just-changed configuration is always re-read.
 */
class GuessEvaluator implements ResetInterface
{
    /** @var array<string, array<string, CompetitionRuleConfiguration>> competition UUID → stored rows by rule identifier */
    private array $configCache = [];

    public function __construct(
        private readonly RuleRegistry $ruleRegistry,
        private readonly CompetitionRuleConfigurationRepository $configurationRepository,
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

            $hit = $rule->evaluate($guess, $match);

            if (1 !== $hit) {
                continue;
            }

            $evaluation->addRulePoints(new GuessEvaluationRulePoints(
                id: $this->identity->next(),
                evaluation: $evaluation,
                ruleIdentifier: $ruleIdentifier,
                points: null === $configuration ? $rule->defaultPoints : $configuration->points,
            ));
        }

        return $evaluation;
    }

    public function forgetCompetition(Uuid $competitionId): void
    {
        unset($this->configCache[$competitionId->toRfc4122()]);
    }

    /**
     * Kernel/worker reset (autoconfigured via {@see ResetInterface}) — drops the
     * config cache between requests/messages/tests so stale rows never leak.
     */
    public function reset(): void
    {
        $this->configCache = [];
    }

    /**
     * @return array<string, CompetitionRuleConfiguration>
     */
    private function configurationsFor(Uuid $competitionId): array
    {
        $key = $competitionId->toRfc4122();

        return $this->configCache[$key] ??= $this->configurationRepository->mapForCompetition($competitionId);
    }
}
