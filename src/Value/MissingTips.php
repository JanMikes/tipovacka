<?php

declare(strict_types=1);

namespace App\Value;

/**
 * How many matches of ONE competition the viewer still owes a tip for — untipped
 * AND still tippable — plus the earliest of those deadlines.
 *
 * „Still tippable" is the whole point: a match whose deadline has passed is not
 * missing a tip, it is „Netipováno" (B5) — a fact, not a call to action. Produced
 * only by {@see \App\Service\Competition\MissingTipCounter}, so every surface that
 * shows the number shows the same number.
 */
final readonly class MissingTips
{
    public function __construct(
        public int $count,
        /** Earliest deadline among the missing ones — the „Tipuj do …" moment; null when nothing is missing. */
        public ?\DateTimeImmutable $earliestDeadline = null,
    ) {
    }

    public static function none(): self
    {
        return new self(0, null);
    }
}
