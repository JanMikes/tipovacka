<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\GuessEvaluationRulePoints;
use App\Entity\SportMatch;
use App\Exception\RuleNotRegistered;
use App\Repository\TournamentRuleConfigurationRepository;
use App\Rule\RuleRegistry;
use App\Service\Identity\ProvideIdentity;

final readonly class GuessEvaluator
{
    public function __construct(
        private RuleRegistry $ruleRegistry,
        private TournamentRuleConfigurationRepository $configurationRepository,
        private ProvideIdentity $identity,
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

        $configurations = $this->configurationRepository->getEnabledForTournament($match->tournament->id);

        $evaluation = new GuessEvaluation(
            id: $this->identity->next(),
            guess: $guess,
            evaluatedAt: $now,
        );

        foreach ($configurations as $ruleIdentifier => $configuration) {
            try {
                $rule = $this->ruleRegistry->get($ruleIdentifier);
            } catch (\LogicException) {
                throw RuleNotRegistered::withIdentifier($ruleIdentifier);
            }

            $hit = $rule->evaluate($guess, $match);

            if (1 !== $hit) {
                continue;
            }

            $evaluation->addRulePoints(new GuessEvaluationRulePoints(
                id: $this->identity->next(),
                evaluation: $evaluation,
                ruleIdentifier: $ruleIdentifier,
                points: $configuration->points,
            ));
        }

        return $evaluation;
    }
}
