<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionRuleConfiguration;

use App\Repository\CompetitionRuleConfigurationRepository;
use App\Repository\GuessEvaluationRepository;
use App\Rule\RuleRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Merge semantics MUST stay in agreement with {@see \App\Service\Scoring\GuessEvaluator}:
 * a stored row always wins; a registered rule with no stored row falls back to the
 * rule's `enabledByDefault` + `defaultPoints`.
 */
#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCompetitionRuleConfigurationQuery
{
    public function __construct(
        private RuleRegistry $ruleRegistry,
        private CompetitionRuleConfigurationRepository $configurationRepository,
        private GuessEvaluationRepository $evaluationRepository,
    ) {
    }

    public function __invoke(GetCompetitionRuleConfiguration $query): CompetitionRuleConfigurationResult
    {
        $indexed = $this->configurationRepository->mapForCompetition($query->competitionId);

        $items = [];

        foreach ($this->ruleRegistry->all() as $rule) {
            $configuration = $indexed[$rule->identifier] ?? null;

            $items[] = new RuleConfigurationItem(
                identifier: $rule->identifier,
                label: $rule->label,
                description: $rule->description,
                enabled: null === $configuration ? $rule->enabledByDefault : $configuration->enabled,
                points: null === $configuration ? $rule->defaultPoints : $configuration->points,
                defaultPoints: $rule->defaultPoints,
            );
        }

        return new CompetitionRuleConfigurationResult(
            items: $items,
            evaluationCount: $this->evaluationRepository->countForCompetition($query->competitionId),
        );
    }
}
