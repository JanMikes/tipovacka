<?php

declare(strict_types=1);

namespace App\Command\SetSportMatchFinalScore;

use Symfony\Component\Uid\Uuid;

final readonly class SetSportMatchFinalScoreCommand
{
    public function __construct(
        public Uuid $sportMatchId,
        public Uuid $editorId,
        public int $homeScore,
        public int $awayScore,
    ) {
    }
}
