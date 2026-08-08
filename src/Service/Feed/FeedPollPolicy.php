<?php

declare(strict_types=1);

namespace App\Service\Feed;

use App\Entity\MatchSource;
use App\Repository\SportMatchRepository;

/**
 * How often a feed-bound source is actually fetched. The cron fires every five
 * minutes; this decides which sources that tick is for, so the cost of the
 * whole pipeline is proportional to how much football is happening rather than
 * to the wall clock.
 *
 * Three cadences, driven entirely by the kickoffs we already store:
 *
 *  - HOT (5 min) — something of this source is being played right now: a match
 *    is live, or kicked off within the last few hours. This is the only window
 *    where a result can appear, and the only one worth polling aggressively.
 *  - WARM (30 min) — a match starts within the next few hours or has just
 *    ended: late kickoff corrections and trailing result entry.
 *  - COLD (24 h) — nothing near. One fetch a day still catches fixture and
 *    kickoff changes weeks ahead of anyone tipping them.
 *
 * A never-polled source is always due, so binding a feed takes effect on the
 * next tick instead of a day later.
 */
final readonly class FeedPollPolicy
{
    /** A match is "being played" for this long after kickoff (90 min + stoppages + ET). */
    private const string PLAYING_WINDOW = '-4 hours';

    private const string WARM_LOOKAHEAD = '+6 hours';

    private const string WARM_LOOKBACK = '-24 hours';

    private const int HOT_INTERVAL_SECONDS = 5 * 60;

    private const int WARM_INTERVAL_SECONDS = 30 * 60;

    private const int COLD_INTERVAL_SECONDS = 24 * 60 * 60;

    public function __construct(
        private SportMatchRepository $sportMatches,
    ) {
    }

    public function isDue(MatchSource $source, \DateTimeImmutable $now): bool
    {
        $lastPolled = $source->feedPolledAt;

        if (null === $lastPolled) {
            return true;
        }

        $elapsed = $now->getTimestamp() - $lastPolled->getTimestamp();

        // A clock that moved backwards (DST, container drift) must not freeze
        // polling until it catches up.
        if ($elapsed < 0) {
            return true;
        }

        return $elapsed >= $this->intervalSeconds($source, $now);
    }

    /** Human-readable cadence for the sync report („hot", „warm", „cold"). */
    public function cadence(MatchSource $source, \DateTimeImmutable $now): string
    {
        return match ($this->intervalSeconds($source, $now)) {
            self::HOT_INTERVAL_SECONDS => 'hot',
            self::WARM_INTERVAL_SECONDS => 'warm',
            default => 'cold',
        };
    }

    private function intervalSeconds(MatchSource $source, \DateTimeImmutable $now): int
    {
        if ($this->sportMatches->hasLiveMatch($source->id)
            || $this->sportMatches->hasMatchKickingOffBetween($source->id, $now->modify(self::PLAYING_WINDOW), $now)
        ) {
            return self::HOT_INTERVAL_SECONDS;
        }

        if ($this->sportMatches->hasMatchKickingOffBetween(
            $source->id,
            $now->modify(self::WARM_LOOKBACK),
            $now->modify(self::WARM_LOOKAHEAD),
        )) {
            return self::WARM_INTERVAL_SECONDS;
        }

        return self::COLD_INTERVAL_SECONDS;
    }
}
