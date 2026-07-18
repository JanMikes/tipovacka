<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Entity\Competition;
use App\Entity\CompetitionRuleConfiguration;
use App\Repository\CompetitionRuleConfigurationRepository;
use App\Rule\RuleRegistry;
use App\Service\Identity\ProvideIdentity;

final readonly class CompetitionRuleConfigurationProvisioner
{
    public function __construct(
        private RuleRegistry $ruleRegistry,
        private CompetitionRuleConfigurationRepository $configurationRepository,
        private ProvideIdentity $identity,
    ) {
    }

    /**
     * Idempotently provisions a CompetitionRuleConfiguration row for every registered
     * rule. Existing rows are left untouched — only missing ones are created with
     * default values (rule's enabledByDefault, defaultPoints).
     */
    public function provision(Competition $competition, \DateTimeImmutable $now): void
    {
        $existing = $this->configurationRepository->mapForCompetition($competition->id);

        foreach ($this->ruleRegistry->all() as $rule) {
            if (array_key_exists($rule->identifier, $existing)) {
                continue;
            }

            $configuration = new CompetitionRuleConfiguration(
                id: $this->identity->next(),
                competition: $competition,
                ruleIdentifier: $rule->identifier,
                enabled: $rule->enabledByDefault,
                points: $rule->defaultPoints,
                now: $now,
            );

            $this->configurationRepository->save($configuration);
        }
    }
}
