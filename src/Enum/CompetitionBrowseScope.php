<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Which relationship to a competition the „Soutěže" list renders (item 07).
 * ONE query ({@see \App\Query\ListBrowsableCompetitions\ListBrowsableCompetitionsQuery})
 * and ONE card component serve both — the scope decides which competitions are
 * in and which call to action the card carries.
 */
enum CompetitionBrowseScope: string
{
    /** Competitions the viewer owns („Tvé soutěže" → „Spravovat"). */
    case Organized = 'organizuji';

    /** Publicly discoverable global competitions („Připojit se" / „Otevřít"). */
    case Discoverable = 'verejne';
}
