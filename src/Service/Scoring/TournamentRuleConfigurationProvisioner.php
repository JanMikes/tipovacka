<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Entity\Tournament;
use App\Entity\TournamentRuleConfiguration;
use App\Repository\TournamentRuleConfigurationRepository;
use App\Rule\RuleRegistry;
use App\Service\Identity\ProvideIdentity;

final readonly class TournamentRuleConfigurationProvisioner
{
    public function __construct(
        private RuleRegistry $ruleRegistry,
        private TournamentRuleConfigurationRepository $configurationRepository,
        private ProvideIdentity $identity,
    ) {
    }

    /**
     * Idempotently provisions a TournamentRuleConfiguration row for every registered
     * rule. Existing rows are left untouched — only missing ones are created with
     * default values (enabled, defaultPoints).
     */
    public function provision(Tournament $tournament, \DateTimeImmutable $now): void
    {
        foreach ($this->ruleRegistry->all() as $rule) {
            $existing = $this->configurationRepository->findOne($tournament->id, $rule->identifier);

            if (null !== $existing) {
                continue;
            }

            $configuration = new TournamentRuleConfiguration(
                id: $this->identity->next(),
                tournament: $tournament,
                ruleIdentifier: $rule->identifier,
                enabled: true,
                points: $rule->defaultPoints,
                now: $now,
            );

            $this->configurationRepository->save($configuration);
        }
    }
}
