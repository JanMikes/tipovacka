<?php

declare(strict_types=1);

namespace App\Value;

use App\Entity\SportMatch;

/**
 * „Buying „Počkejte si na sestavy" would let you tip THIS match until THAT
 * moment." Built only by {@see \App\Service\Competition\TipChangeUnlock}, which
 * never returns one unless the purchase would actually gain the viewer something.
 *
 * $deadline is the deadline {@see \App\Service\EffectiveTipDeadlineResolver}
 * itself would return once the boost is owned — not a re-derivation of it.
 */
final readonly class TipChangeUnlockOffer
{
    public function __construct(
        public SportMatch $sportMatch,
        public \DateTimeImmutable $deadline,
    ) {
    }
}
