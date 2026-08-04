<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Which external system a curated MatchSource is bound to for automated match
 * data (fixtures, postponements, results). The adapter for each case lives
 * behind App\Service\Feed\MatchDataProvider; a source with no provider is
 * maintained manually. See .docs/MATCH_DATA_FEEDS.md for the source strategy.
 */
enum FeedProvider: string
{
    /** FAČR IS (is.fotbal.cz) — Czech competitions, post-match zápis o utkání. */
    case Facr = 'facr';

    /** SoccersAPI (soccersapi.com) — UCL/UEL candidate, free 3-league plan. */
    case SoccersApi = 'soccersapi';

    /** API-Football (api-sports.io) — all-leagues fallback vendor. */
    case ApiFootball = 'api_football';

    /** Local JSON file (feedRef = path) — tests, dev dry runs, staged rollouts. */
    case Fixture = 'fixture';

    public function label(): string
    {
        return match ($this) {
            self::Facr => 'FAČR',
            self::SoccersApi => 'SoccersAPI',
            self::ApiFootball => 'API-Football',
            self::Fixture => 'Soubor (JSON)',
        };
    }
}
