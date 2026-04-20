<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Guess;
use App\Entity\SportMatch;

#[AsRule]
final class CorrectHomeGoalsRule implements Rule
{
    public string $identifier { get => 'correct_home_goals'; }

    public string $label { get => 'Počet gólů domácí'; }

    public string $description { get => 'Uhádnutý počet gólů domácího týmu.'; }

    public int $defaultPoints { get => 1; }

    public function evaluate(Guess $guess, SportMatch $match): int
    {
        if (null === $match->homeScore || null === $match->awayScore) {
            return 0;
        }

        return $guess->homeScore === $match->homeScore ? 1 : 0;
    }
}
