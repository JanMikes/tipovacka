<?php

declare(strict_types=1);

namespace App\Query\GetGuessesForMatchInCompetition;

use Symfony\Component\Uid\Uuid;

final readonly class GuessForMatchItem
{
    /**
     * @param list<array{int, int}>|null $periodScores
     * @param list<string>               $scorerNames
     */
    public function __construct(
        public Uuid $userId,
        public string $nickname,
        public ?int $homeScore,
        public ?int $awayScore,
        public \DateTimeImmutable $submittedAt,
        public \DateTimeImmutable $updatedAt,
        public bool $isMine,
        public bool $hidden = false,
        public ?array $periodScores = null,
        public ?int $overtimeHomeScore = null,
        public ?int $overtimeAwayScore = null,
        public array $scorerNames = [],
    ) {
    }
}
