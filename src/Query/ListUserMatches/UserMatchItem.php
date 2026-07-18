<?php

declare(strict_types=1);

namespace App\Query\ListUserMatches;

use Symfony\Component\Uid\Uuid;

final readonly class UserMatchItem
{
    public function __construct(
        public Uuid $id,
        public Uuid $matchSourceId,
        public string $matchSourceName,
        public string $homeTeam,
        public string $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public ?string $venue,
        public ?string $round,
        public bool $isOpenForGuesses,
        public bool $isFinished,
        public bool $isLive,
        public bool $isPostponed,
        public ?int $homeScore,
        public ?int $awayScore,
        public int $competitionsCount,
        public int $guessedCompetitionsCount,
        public int $pendingCompetitionsCount,
    ) {
    }
}
