<?php

declare(strict_types=1);

namespace App\Service\Scoring;

use App\Entity\MatchSource;
use App\Entity\MatchSourceRuleConfiguration;
use App\Repository\MatchSourceRuleConfigurationRepository;
use App\Rule\RuleRegistry;
use App\Service\Identity\ProvideIdentity;

final readonly class MatchSourceRuleConfigurationProvisioner
{
    public function __construct(
        private RuleRegistry $ruleRegistry,
        private MatchSourceRuleConfigurationRepository $configurationRepository,
        private ProvideIdentity $identity,
    ) {
    }

    /**
     * Idempotently provisions a MatchSourceRuleConfiguration row for every registered
     * rule. Existing rows are left untouched — only missing ones are created with
     * default values (enabled, defaultPoints).
     */
    public function provision(MatchSource $matchSource, \DateTimeImmutable $now): void
    {
        foreach ($this->ruleRegistry->all() as $rule) {
            $existing = $this->configurationRepository->findOne($matchSource->id, $rule->identifier);

            if (null !== $existing) {
                continue;
            }

            $configuration = new MatchSourceRuleConfiguration(
                id: $this->identity->next(),
                matchSource: $matchSource,
                ruleIdentifier: $rule->identifier,
                enabled: true,
                points: $rule->defaultPoints,
                now: $now,
            );

            $this->configurationRepository->save($configuration);
        }
    }
}
