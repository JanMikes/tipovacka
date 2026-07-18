<?php

declare(strict_types=1);

namespace App\Query\GetMatchSourceRuleConfiguration;

use App\Repository\GuessEvaluationRepository;
use App\Repository\MatchSourceRuleConfigurationRepository;
use App\Rule\RuleRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetMatchSourceRuleConfigurationQuery
{
    public function __construct(
        private RuleRegistry $ruleRegistry,
        private MatchSourceRuleConfigurationRepository $configurationRepository,
        private GuessEvaluationRepository $evaluationRepository,
    ) {
    }

    public function __invoke(GetMatchSourceRuleConfiguration $query): MatchSourceRuleConfigurationResult
    {
        $configurations = $this->configurationRepository->listForMatchSource($query->matchSourceId);
        $indexed = [];

        foreach ($configurations as $configuration) {
            $indexed[$configuration->ruleIdentifier] = $configuration;
        }

        $items = [];

        foreach ($this->ruleRegistry->all() as $rule) {
            $configuration = $indexed[$rule->identifier] ?? null;

            $items[] = new RuleConfigurationItem(
                identifier: $rule->identifier,
                label: $rule->label,
                description: $rule->description,
                enabled: null === $configuration ? true : $configuration->enabled,
                points: null === $configuration ? $rule->defaultPoints : $configuration->points,
                defaultPoints: $rule->defaultPoints,
            );
        }

        return new MatchSourceRuleConfigurationResult(
            items: $items,
            evaluationCount: $this->evaluationRepository->countForMatchSource($query->matchSourceId),
        );
    }
}
