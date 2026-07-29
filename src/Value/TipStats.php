<?php

declare(strict_types=1);

namespace App\Value;

use App\Enum\CompetitionMonetization;
use Symfony\Component\Uid\Uuid;

/**
 * One match's tip statistics AS SEEN BY ONE VIEWER inside one competition —
 * everything the „Rozložení tipů" surface needs, resolved once and rendered many
 * times (see {@see \App\Service\Competition\TipStatsProvider}).
 *
 * The split (1 / X / 2) is present only when {@see $visible}; the {@see $total}
 * is always present because the paywall advertises „uvidíte, jak tipuje X hráčů"
 * and a bare count leaks nothing about anyone's tip.
 */
/* Not a `readonly class`: the virtual properties below are hooked, and hooked
   properties may not be readonly — the data is immutable per-property instead. */
final class TipStats
{
    public function __construct(
        public readonly Uuid $competitionId,
        public readonly string $competitionName,
        public readonly CompetitionMonetization $monetization,
        public readonly bool $visible,
        /* Deadline-INDEPENDENT entitlement (premium toggle / own boost). `visible`
           is this OR-ed with „the deadline passed", so the two together say WHY the
           split is readable — which is all the „✓ PRÉMIUM" / „po uzávěrce" badge on
           the full card needs. */
        public readonly bool $entitled,
        public readonly int $total,
        public readonly int $homeWinPercent,
        public readonly int $drawPercent,
        public readonly int $awayWinPercent,
        /* Absolute counts behind the percentages — the full („Rozložení tipů")
           card labels each bar with both. Zeroed together with the percentages
           when the viewer is not entitled, so a locked card leaks nothing. */
        public readonly int $homeWinCount,
        public readonly int $drawCount,
        public readonly int $awayWinCount,
        /** The viewer could buy the unlocking boost right now (boosts competition, not entitled, is a member). */
        public readonly bool $purchasable,
        public readonly int $price,
        public readonly int $balance,
    ) {
    }

    /**
     * Something worth rendering. Locked always shows — the paywall is the whole
     * point, and it must not vanish just because nobody has tipped yet (it drops
     * the player count from its copy instead). Unlocked needs at least one tip:
     * an empty bar draws nothing.
     */
    public bool $hasAnythingToShow {
        get => !$this->visible || $this->total > 0;
    }

    public bool $canAfford {
        get => $this->balance >= $this->price;
    }
}
