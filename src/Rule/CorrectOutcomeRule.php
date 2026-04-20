<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Guess;
use App\Entity\SportMatch;

#[AsRule]
final class CorrectOutcomeRule implements Rule
{
    public string $identifier { get => 'correct_outcome'; }

    public string $label { get => 'Správný tip výsledku'; }

    public string $description { get => 'Uhádnutá výhra domácích / remíza / výhra hostů.'; }

    public int $defaultPoints { get => 3; }

    public function evaluate(Guess $guess, SportMatch $match): int
    {
        if (null === $match->homeScore || null === $match->awayScore) {
            return 0;
        }

        return $this->sign($guess->homeScore - $guess->awayScore)
            === $this->sign($match->homeScore - $match->awayScore)
            ? 1
            : 0;
    }

    private function sign(int $value): int
    {
        return $value <=> 0;
    }
}
