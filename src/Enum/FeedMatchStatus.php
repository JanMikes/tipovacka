<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The neutral match-status vocabulary of the feed layer. Every provider adapter
 * owns a mapping table from its vendor's codes (API-Football "PST", SoccersAPI
 * status 4, FAČR text) into these six values; a code the adapter cannot map
 * becomes Unknown and is reported loudly by the synchronizer, never guessed.
 */
enum FeedMatchStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Finished = 'finished';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';
}
