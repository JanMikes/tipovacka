<?php

declare(strict_types=1);

namespace App\Query\GetGuessesForMatchInGroup;

use Symfony\Component\Uid\Uuid;

final readonly class GuessForMatchItem
{
    public function __construct(
        public Uuid $userId,
        public string $nickname,
        public int $homeScore,
        public int $awayScore,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
        public bool $isMine,
    ) {
    }
}
