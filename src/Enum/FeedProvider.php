<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Which external system a curated MatchSource is bound to for automated match
 * data (fixtures, postponements, results). The adapter for each case lives
 * behind App\Service\Feed\MatchDataProvider; a source with no provider is
 * maintained manually. See .docs/MATCH_DATA_FEEDS.md for the source strategy.
 *
 * Three real providers, because no single vendor covers what wtips offers:
 * FAČR is the free system of record for every Czech soutěž, UEFA's own API is
 * the free system of record for its three club competitions, and Sportmonks is
 * the paid subscription covering the leagues neither of them files.
 */
enum FeedProvider: string
{
    /** FAČR IS (is.fotbal.cz) — every Czech soutěž; feedRef = soutěž detail GUID. */
    case Facr = 'facr';

    /** UEFA open API (match.uefa.com) — UCL/UEL/UECL; feedRef = competitionId (1/14/2019). */
    case Uefa = 'uefa';

    /** Sportmonks v3 — the subscribed leagues; feedRef = league id (8, 82, 564, …). */
    case Sportmonks = 'sportmonks';

    /** Local JSON file (feedRef = path) — tests, dev dry runs, staged rollouts. */
    case Fixture = 'fixture';

    public function label(): string
    {
        return match ($this) {
            self::Facr => 'FAČR',
            self::Uefa => 'UEFA',
            self::Sportmonks => 'Sportmonks',
            self::Fixture => 'Soubor (JSON)',
        };
    }

    /**
     * Whether the provider reports the complete goal-scorer sheet. A score-only
     * provider emits `events: null`, which never overwrites manually entered
     * scorers — see MatchSnapshot::$events and FeedSynchronizer::writeFinalScore.
     */
    public function reportsScorers(): bool
    {
        return match ($this) {
            // The FAČR zápis o utkání (the only place scorers live) is behind a
            // login; the public soutěž page carries scores but no scorers.
            self::Facr => false,
            self::Uefa, self::Sportmonks, self::Fixture => true,
        };
    }
}
