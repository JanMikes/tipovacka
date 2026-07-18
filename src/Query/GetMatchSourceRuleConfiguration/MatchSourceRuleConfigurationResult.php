<?php

declare(strict_types=1);

namespace App\Query\GetMatchSourceRuleConfiguration;

final readonly class MatchSourceRuleConfigurationResult
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
