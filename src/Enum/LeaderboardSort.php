<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * „Seřadit" on the Žebříček page. Ordering only — the POZICE column always shows
 * the real rank, so re-sorting never rewrites anybody's standing.
 */
enum LeaderboardSort: string
{
    case Points = 'body';
    case Accuracy = 'uspesnost';
    case Exact = 'presne';
    case Streak = 'streak';

    public static function fromRequest(?string $value): self
    {
        return (null !== $value ? self::tryFrom($value) : null) ?? self::Points;
    }

    public function label(): string
    {
        return match ($this) {
            self::Points => 'Body',
            self::Accuracy => 'Úspěšnost',
            self::Exact => 'Přesné',
            self::Streak => 'Streak',
        };
    }
}
