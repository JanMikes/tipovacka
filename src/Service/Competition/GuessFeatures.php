<?php

declare(strict_types=1);

namespace App\Service\Competition;

/**
 * Resolved guess-feature toggles of ONE competition. Feature toggles ARE rule
 * enablement (DOMAIN.md §Scoring) — there are no duplicate flags:
 * periods ⇔ period_exact OR period_tendency, scorers ⇔ scorer_hit,
 * overtime ⇔ overtime_exact.
 */
final readonly class GuessFeatures
{
    public function __construct(
        public bool $periodTips,
        public bool $scorerTips,
        public bool $overtimeTip,
    ) {
    }
}
