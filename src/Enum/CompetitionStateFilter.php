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
 * Item 15 removed both filter bars from `/souteze`, so nothing renders these as
 * chips any more. The enum survives because {@see \App\Query\ListBrowsableCompetitions\BrowsableCompetitionItem::$state}
 * still derives a competition's state from its matches, and
 * {@see \App\Query\ListBrowsableCompetitions\ListBrowsableCompetitions} still
 * accepts it as an optional scope. The per-context chip list (`forScope()`) went
 * with the bars — nothing decided anything with it any more.
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
