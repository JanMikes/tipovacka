<?php

declare(strict_types=1);

namespace App\Query\GetSportMatchDetail;

use App\Enum\SportMatchState;
use App\Value\TeamView;
use Symfony\Component\Uid\Uuid;

final readonly class SportMatchDetailResult
{
    public function __construct(
        public Uuid $id,
        public Uuid $matchSourceId,
        public string $matchSourceName,
        public TeamView $homeTeam,
        public TeamView $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public ?string $venue,
        public SportMatchState $state,
        public ?int $homeScore,
        public ?int $awayScore,
        public bool $isOpenForGuesses,
    ) {
    }
}
