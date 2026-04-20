<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Component\Uid\Uuid;

final readonly class GuessUpdated
{
    public function __construct(
        public Uuid $guessId,
        public int $homeScore,
        public int $awayScore,
        public \DateTimeImmutable $occurredOn,
    ) {
    }
}
