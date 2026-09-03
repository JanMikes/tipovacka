<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionRuleConfiguration;

use App\Enum\OvertimeCoverage;
use App\Repository\CompetitionRuleConfigurationRepository;
use App\Repository\CompetitionSourceRepository;
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
        private CompetitionSourceRepository $sourceRepository,
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

        // Derived here rather than in Twig: whether the overtime rule could ever
        // score is a fact about the soutěž's zdroje, not a rendering decision.
        $overtimeCounts = $this->sourceRepository->overtimeSourceCounts($query->competitionId);

        return new CompetitionRuleConfigurationResult(
            items: $items,
            evaluationCount: $this->evaluationRepository->countForCompetition($query->competitionId),
            overtimeCoverage: OvertimeCoverage::fromCounts($overtimeCounts['total'], $overtimeCounts['withOvertime']),
        );
    }
}
