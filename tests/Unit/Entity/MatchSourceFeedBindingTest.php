<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Enum\FeedProvider;
use App\Tests\Unit\Service\Feed\FeedSourceFactory;
use PHPUnit\Framework\TestCase;

/**
 * Rebinding a source is not a small edit: everything the previous feed taught
 * us about it is stale, and the poll stamp is what an adapter reads to decide
 * between „first fetch, show me the season" and „steady state, show me the
 * window". Premier League hit this on 2026-08-08 — it had been polled while
 * bound to a JSON file, so binding it to Sportmonks still looked like a routine
 * poll and adoption saw 30 fixtures instead of 380.
 */
final class MatchSourceFeedBindingTest extends TestCase
{
    private const string NOW = '2026-08-08 12:00:00';

    public function testRebindingToAnotherProviderClearsThePollStamp(): void
    {
        $source = FeedSourceFactory::create(FeedProvider::Fixture, 'seed/feeds/pl.json');
        $source->markFeedPolled($this->now());
        self::assertNotNull($source->feedPolledAt);

        $source->bindFeed(FeedProvider::Sportmonks, '8', $this->now());

        self::assertNull($source->feedPolledAt);
    }

    public function testChangingOnlyTheReferenceAlsoClearsIt(): void
    {
        $source = FeedSourceFactory::create(FeedProvider::Sportmonks, '8');
        $source->markFeedPolled($this->now());

        $source->bindFeed(FeedProvider::Sportmonks, '82', $this->now());

        self::assertNull($source->feedPolledAt);
    }

    /** Re-binding to exactly what is already there must not restart the cadence. */
    public function testRebindingToTheSameFeedKeepsTheStamp(): void
    {
        $source = FeedSourceFactory::create(FeedProvider::Sportmonks, '8');
        $source->markFeedPolled($this->now());

        $source->bindFeed(FeedProvider::Sportmonks, '8', $this->now());

        self::assertEquals($this->now(), $source->feedPolledAt);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW, new \DateTimeZone('UTC'));
    }
}
