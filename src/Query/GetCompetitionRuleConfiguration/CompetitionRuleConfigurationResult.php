<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionRuleConfiguration;

use App\Enum\OvertimeCoverage;

/**
 * The soutěž's scoring rules as its rule screen shows them, plus the two facts
 * the screen needs about them: how many evaluations a change would recalculate,
 * and how much of the soutěž's scope can go on to extra time at all. The
 * overtime rule is offered whatever the coverage says — a soutěž can span zdroje
 * with different flags, and a flag can be switched on later — but a scope that
 * never plays extra time is said out loud instead of silently never scoring.
 */
final readonly class CompetitionRuleConfigurationResult
{
    /**
     * @param list<RuleConfigurationItem> $items
     */
    public function __construct(
        public array $items,
        public int $evaluationCount,
        public OvertimeCoverage $overtimeCoverage,
    ) {
    }
}
