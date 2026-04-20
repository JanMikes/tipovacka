<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Guess;
use App\Entity\SportMatch;

#[AsRule]
final class ExactScoreRule implements Rule
{
    public string $identifier { get => 'exact_score'; }

    public string $label { get => 'Přesný výsledek'; }

    public string $description { get => 'Uhádnuté přesné skóre zápasu.'; }

    public int $defaultPoints { get => 5; }

    public function evaluate(Guess $guess, SportMatch $match): int
    {
        if (null === $match->homeScore || null === $match->awayScore) {
            return 0;
        }

        return $guess->homeScore === $match->homeScore
            && $guess->awayScore === $match->awayScore
            ? 1
            : 0;
    }
}
