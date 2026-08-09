<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Feed;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Service\Feed\FeedPollPolicy;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The cadence is what keeps the five-minute cron cheap: it decides which sources
 * a given tick is actually for. The fixture source holds MATCH_LIVE, which
 * kicked off at 2025-06-15 11:00 — one hour before the frozen clock.
 *
 * Note the policy takes `$now` as an argument rather than reading the clock, so
 * these cases are just arithmetic — no clock travel needed.
 */
final class FeedPollPolicyTest extends IntegrationTestCase
{
    public function testAMatchBeingPlayedMakesItsSourceHot(): void
    {
        self::assertSame('hot', $this->cadenceAt('2025-06-15 12:00:00 UTC'));
    }

    /**
     * The bound that matters. Live is a state we enter and leave on the feed's
     * word, so a fixture the provider abandons mid-game never leaves it. Without
     * the kickoff bound that single row would hold this source at 288 fetches a
     * day for the rest of the season.
     */
    public function testAMatchStuckLiveDoesNotPinTheSourceHotForEver(): void
    {
        // A full day after that kickoff: nothing here is being played any more,
        // whatever the stored state still claims.
        self::assertSame('cold', $this->cadenceAt('2025-06-16 12:00:00 UTC'));
    }

    /** A kickoff a few hours out is worth 30 minutes, not 5 and not a day. */
    public function testAKickoffLaterTodayIsWarm(): void
    {
        // MATCH_SCHEDULED kicks off 2025-06-20 18:00 — four hours ahead here.
        self::assertSame('warm', $this->cadenceAt('2025-06-20 14:00:00 UTC'));
    }

    public function testANeverPolledSourceIsAlwaysDue(): void
    {
        $source = $this->source();
        self::assertNull($source->feedPolledAt);

        self::assertTrue($this->policy()->isDue($source, new \DateTimeImmutable('2025-06-15 12:00:00 UTC')));
    }

    public function testAColdSourceIsNotDueAgainWithinTheDay(): void
    {
        $source = $this->source();
        $source->markFeedPolled(new \DateTimeImmutable('2025-06-16 11:00:00 UTC'));

        $now = new \DateTimeImmutable('2025-06-16 12:00:00 UTC');

        self::assertSame('cold', $this->policy()->cadence($source, $now));
        self::assertFalse($this->policy()->isDue($source, $now));
    }

    private function cadenceAt(string $now): string
    {
        return $this->policy()->cadence($this->source(), new \DateTimeImmutable($now));
    }

    private function source(): MatchSource
    {
        $source = $this->entityManager()->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $source);

        return $source;
    }

    private function policy(): FeedPollPolicy
    {
        /** @var FeedPollPolicy $policy */
        $policy = self::getContainer()->get(FeedPollPolicy::class);

        return $policy;
    }
}
