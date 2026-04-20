<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Guess;
use App\Entity\SportMatch;

#[AsRule]
final class CorrectAwayGoalsRule implements Rule
{
    public string $identifier { get => 'correct_away_goals'; }

    public string $label { get => 'Počet gólů hosté'; }

    public string $description { get => 'Uhádnutý počet gólů hostujícího týmu.'; }

    public int $defaultPoints { get => 1; }

    public function evaluate(Guess $guess, SportMatch $match): int
    {
        if (null === $match->homeScore || null === $match->awayScore) {
            return 0;
        }

        return $guess->awayScore === $match->awayScore ? 1 : 0;
    }
}
