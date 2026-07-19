<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Leaderboard time window (screenshot 13: Celkem / Poslední kolo / Týden /
 * Měsíc). Only the two windows below ship in S12 — each is a single `enum`
 * case, so the tabs render straight from {@see cases()} and a future window is
 * one case + one branch in {@see \App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboardQuery}.
 */
enum LeaderboardTimeFilter: string
{
    case AllTime = 'celkem';
    case Last7Days = '7dni';

    public static function fromRequest(?string $value): self
    {
        return (null !== $value ? self::tryFrom($value) : null) ?? self::AllTime;
    }

    public function label(): string
    {
        return match ($this) {
            self::AllTime => 'Celkem',
            self::Last7Days => 'Posledních 7 dní',
        };
    }
}
