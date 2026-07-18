<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionRuleConfiguration;

final readonly class CompetitionRuleConfigurationResult
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
