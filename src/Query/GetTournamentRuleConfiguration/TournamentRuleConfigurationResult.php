<?php

declare(strict_types=1);

namespace App\Query\GetTournamentRuleConfiguration;

final readonly class TournamentRuleConfigurationResult
{
    /**
     * @param list<RuleConfigurationItem> $items
     */
    public function __construct(
        public array $items,
        public int $evaluationCount,
    ) {
    }
}
