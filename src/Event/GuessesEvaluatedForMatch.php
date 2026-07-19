<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

/**
 * All active guesses for a match have just been evaluated (first finish only,
 * not score corrections). Recorded on the {@see \App\Entity\SportMatch} by the
 * {@see SportMatchFinishedHandler} so it is dispatched by the domain-event
 * middleware AFTER the evaluations are committed — the `match_evaluated`
 * notification handler can then read a correct post-evaluation leaderboard.
 */
final readonly class GuessesEvaluatedForMatch
{
    public function __construct(
        public Uuid $sportMatchId,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
