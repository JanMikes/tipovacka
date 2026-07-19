<?php

declare(strict_types=1);

namespace App\Rule;

use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Service\Scoring\MatchContext;

#[AsRule]
final class ExactScoreRule implements Rule
{
    public const string IDENTIFIER = 'exact_score';

    public string $identifier { get => self::IDENTIFIER; }

    public string $label { get => 'Přesný výsledek'; }

    public string $description { get => 'Uhádnuté přesné skóre zápasu.'; }

    public int $defaultPoints { get => 5; }

    public bool $enabledByDefault { get => true; }

    public string $category { get => 'base'; }

    public function evaluate(Guess $guess, SportMatch $match, MatchContext $context): int
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
