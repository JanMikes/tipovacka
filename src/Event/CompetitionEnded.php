<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/**
 * Recorded once, when a competition is first detected as truly over (see
 * {@see \App\Entity\Competition::markEndedNotified}). Decouples side effects of
 * "the competition ended" from the detection: S11 sends the final-standing
 * notifications inline, while S12 consumes this event to capture the FINAL
 * leaderboard snapshot — neither knows about the other.
 */
final readonly class CompetitionEnded
{
    public function __construct(
        public Uuid $competitionId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
