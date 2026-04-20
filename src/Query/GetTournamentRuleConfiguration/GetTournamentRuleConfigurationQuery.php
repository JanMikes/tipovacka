<?php

declare(strict_types=1);

namespace App\Query\GetTournamentRuleConfiguration;

use App\Repository\GuessEvaluationRepository;
use App\Repository\TournamentRuleConfigurationRepository;
use App\Rule\RuleRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetTournamentRuleConfigurationQuery
{
    public function __construct(
        private RuleRegistry $ruleRegistry,
        private TournamentRuleConfigurationRepository $configurationRepository,
        private GuessEvaluationRepository $evaluationRepository,
    ) {
    }

    public function __invoke(GetTournamentRuleConfiguration $query): TournamentRuleConfigurationResult
    {
        $configurations = $this->configurationRepository->listForTournament($query->tournamentId);
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

        return new TournamentRuleConfigurationResult(
            items: $items,
            evaluationCount: $this->evaluationRepository->countForTournament($query->tournamentId),
        );
    }
}
