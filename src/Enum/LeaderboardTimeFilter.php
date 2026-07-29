<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Leaderboard period filter (Celkem / Poslední kolo / Týden / Měsíc). Each window
 * is a single `enum` case, so the tabs render straight from {@see cases()} —
 * declaration order IS tab order — and a future window is one case + one branch
 * in {@see \App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboardQuery}.
 *
 * Not every case is a *time* window: {@see LastRound} slices by
 * `SportMatch::$round` instead, resolved through
 * {@see \App\Service\Competition\CompetitionRoundResolver}.
 */
enum LeaderboardTimeFilter: string
{
    case AllTime = 'celkem';
    case LastRound = 'kolo';
    case Last7Days = '7dni';
    case Last30Days = 'mesic';

    public static function fromRequest(?string $value): self
    {
        return (null !== $value ? self::tryFrom($value) : null) ?? self::AllTime;
    }

    public function label(): string
    {
        return match ($this) {
            self::AllTime => 'Celkem',
            self::LastRound => 'Poslední kolo',
            self::Last7Days => 'Týden',
            self::Last30Days => 'Měsíc',
        };
    }

    /**
     * Longer wording for places that need the window spelled out (tooltips, the
     * „stav po …" caption) rather than the compact tab label.
     */
    public function longLabel(): string
    {
        return match ($this) {
            self::AllTime => 'Celkem',
            self::LastRound => 'Poslední kolo',
            self::Last7Days => 'Posledních 7 dní',
            self::Last30Days => 'Posledních 30 dní',
        };
    }
}
