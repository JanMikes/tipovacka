<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * „Stav" chips of the shared competition filter bar (item 07). A competition's
 * state is derived from its INCLUDED matches (never from the calendar alone):
 *
 * - {@see Finished}   — the match source is completed, or every included match is settled;
 * - {@see Upcoming}   — nothing has kicked off yet;
 * - {@see Running}    — anything in between.
 *
 * The public/global list never offers {@see Finished}: a global competition
 * over a completed source is not discoverable at all (it cannot be joined), so
 * the chip would always resolve to an empty list. Which chips a context offers
 * is therefore a per-context list — see {@see forScope()}.
 */
enum CompetitionStateFilter: string
{
    case All = 'vse';
    case Upcoming = 'nadchazejici';
    case Running = 'probihajici';
    case Finished = 'skoncene';

    public static function fromRequest(?string $value): self
    {
        return (null !== $value ? self::tryFrom($value) : null) ?? self::All;
    }

    /**
     * @return list<self>
     */
    public static function forScope(CompetitionBrowseScope $scope): array
    {
        if (CompetitionBrowseScope::Discoverable === $scope) {
            return [self::All, self::Upcoming, self::Running];
        }

        return self::cases();
    }

    public function label(): string
    {
        return match ($this) {
            self::All => 'Všechny',
            self::Upcoming => 'Nadcházející',
            self::Running => 'Probíhající',
            self::Finished => 'Skončené',
        };
    }
}
