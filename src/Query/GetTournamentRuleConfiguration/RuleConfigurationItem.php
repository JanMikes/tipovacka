<?php

declare(strict_types=1);

namespace App\Query\GetTournamentRuleConfiguration;

final readonly class RuleConfigurationItem
{
    public function __construct(
        public string $identifier,
        public string $label,
        public string $description,
        public bool $enabled,
        public int $points,
        public int $defaultPoints,
    ) {
    }
}
