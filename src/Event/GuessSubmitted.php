<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class GuessSubmitted
{
    public function __construct(
        public Uuid $guessId,
        public Uuid $userId,
        public Uuid $sportMatchId,
        public Uuid $groupId,
        public int $homeScore,
        public int $awayScore,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
