<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Feed;

use App\Enum\FeedMatchStatus;
use App\Enum\FeedProvider;
use App\Enum\MatchSide;
use App\Exception\FeedPayloadInvalid;
use App\Service\Feed\SportmonksMatchDataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Locks the shape of Sportmonks v3 `fixtures/between`. The payload is trimmed
 * from real Premier League responses (fetched 2026-08-08): participants carrying
 * their side in `meta.location`, scores as one CURRENT row per participant, and
 * `events[].type_id` 14/16 = goal, 15 = own goal.
 */
final class SportmonksMatchDataProviderTest extends TestCase
{
    public function testParsesScheduledFixture(): void
    {
        $scheduled = $this->fetch()[0];

        self::assertSame('19722203', $scheduled->externalId);
        self::assertSame('Arsenal', $scheduled->homeTeamName, 'home comes from meta.location, never array order');
        self::assertSame('Coventry City', $scheduled->awayTeamName);
        self::assertSame(FeedMatchStatus::Scheduled, $scheduled->status);
        self::assertSame('2026-08-21 19:00:00', $scheduled->kickoffUtc->format('Y-m-d H:i:s'));
        self::assertSame('1. kolo', $scheduled->round);
        self::assertSame('Emirates Stadium', $scheduled->venue);
        self::assertNull($scheduled->homeScore);
    }

    public function testParsesFinishedFixtureWithScorers(): void
    {
        $finished = $this->fetch()[1];

        self::assertSame(FeedMatchStatus::Finished, $finished->status);
        self::assertSame(0, $finished->homeScore);
        self::assertSame(3, $finished->awayScore);

        self::assertNotNull($finished->events);
        self::assertCount(2, $finished->events, 'the own goal is excluded');
        self::assertSame('Patrick Dorgu', $finished->events[0]->playerName);
        self::assertSame(MatchSide::Away, $finished->events[0]->side);
        self::assertSame(33, $finished->events[0]->minute);
    }

    public function testInPlayFixtureIsLive(): void
    {
        self::assertSame(FeedMatchStatus::Live, $this->fetch()[2]->status);
    }

    /**
     * SUSPENDED/ABANDONED are deliberately NOT mapped: half a match is not a
     * result, and the synchronizer must ask a human rather than write one.
     */
    public function testAbandonedFixtureIsUnknown(): void
    {
        $abandoned = $this->fetch()[3];

        self::assertSame(FeedMatchStatus::Unknown, $abandoned->status);
        self::assertSame('ABANDONED', $abandoned->rawStatus);
    }

    /**
     * A never-polled source must see the WHOLE season at once, or
     * app:matches:adopt-external-ids can only bridge the fixtures currently in
     * range and every later one enters as a duplicate. Sportmonks caps a range
     * at 100 days, so that means several chunked requests.
     */
    public function testFirstFetchCoversTheSeasonInChunks(): void
    {
        $requests = 0;
        $client = new MockHttpClient(function (string $method, string $url) use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse($this->payload(), ['response_headers' => ['content-type' => 'application/json']]);
        });

        $provider = new SportmonksMatchDataProvider(
            $client,
            new MockClock(new \DateTimeImmutable('2026-08-08 12:00:00', new \DateTimeZone('UTC'))),
            'test-key',
        );

        $provider->fetchMatches(FeedSourceFactory::create(FeedProvider::Sportmonks, '8'));

        self::assertGreaterThan(1, $requests, 'a season does not fit in one 100-day request');
    }

    /** Once polled, the steady state is a single windowed request. */
    public function testLaterFetchesAreOneWindowedRequest(): void
    {
        $requests = 0;
        $client = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse($this->payload(), ['response_headers' => ['content-type' => 'application/json']]);
        });

        $source = FeedSourceFactory::create(FeedProvider::Sportmonks, '8');
        $source->markFeedPolled(new \DateTimeImmutable('2026-08-08 11:00:00', new \DateTimeZone('UTC')));

        $provider = new SportmonksMatchDataProvider(
            $client,
            new MockClock(new \DateTimeImmutable('2026-08-08 12:00:00', new \DateTimeZone('UTC'))),
            'test-key',
        );

        $provider->fetchMatches($source);

        self::assertSame(1, $requests);
    }

    public function testMissingApiKeyFailsBeforeAnyRequest(): void
    {
        $provider = new SportmonksMatchDataProvider(
            new MockHttpClient([]),
            new MockClock(new \DateTimeImmutable('2026-08-08 12:00:00', new \DateTimeZone('UTC'))),
            null,
        );

        $this->expectException(FeedPayloadInvalid::class);

        $provider->fetchMatches(FeedSourceFactory::create(FeedProvider::Sportmonks, '8'));
    }

    /** Request URLs land in INFO logs (shipped to Sentry Logs): the token must not be in them. */
    public function testTokenTravelsInTheHeaderNeverTheUrl(): void
    {
        $seen = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen[] = ['url' => $url, 'headers' => $options['normalized_headers']['authorization'] ?? []];

            return $this->json($this->payload());
        });

        $this->provider($client)->fetchMatches($this->polledSource());

        self::assertStringNotContainsString('test-key', $seen[0]['url']);
        self::assertSame(['Authorization: test-key'], $seen[0]['headers']);
    }

    /**
     * The 2026-10-28 → 11-17 outage stranded 20 played matches outside the
     * 7-day back window. The window now reaches back to the oldest match still
     * awaiting its result, so the backlog arrives by itself once the feed is back.
     */
    public function testWindowReachesBackToTheOldestMatchAwaitingAResult(): void
    {
        $urls = $this->requestedUrls(new \DateTimeImmutable('2026-07-20 15:00:00 UTC'));

        self::assertCount(1, $urls, '19 + 30 days still fit one request');
        self::assertStringContainsString('/between/2026-07-19/2026-09-07', $urls[0], 'one day of slack before that kickoff');
    }

    public function testNothingAwaitingKeepsTheUsualWindow(): void
    {
        self::assertStringContainsString('/between/2026-08-01/2026-09-07', $this->requestedUrls(null)[0]);
    }

    public function testRecentAwaitingMatchNeverShrinksTheUsualWindow(): void
    {
        $urls = $this->requestedUrls(new \DateTimeImmutable('2026-08-07 15:00:00 UTC'));

        self::assertStringContainsString('/between/2026-08-01/', $urls[0]);
    }

    public function testHealingReachIsCapped(): void
    {
        $urls = $this->requestedUrls(new \DateTimeImmutable('2025-01-01 00:00:00 UTC'));

        // 120 days back from 2026-08-08 = 2026-04-10, chunked under the 100-day limit.
        self::assertStringContainsString('/between/2026-04-10/', $urls[0]);
        self::assertGreaterThan(1, count($urls));
    }

    /**
     * Exactly how the outage looked: HTTP 200, no rows, a message that does not
     * distinguish „nothing there" from „not in your plan" — and a free plan.
     */
    public function testEmptyAnswerForALeagueOutsideTheSubscriptionFails(): void
    {
        $client = new MockHttpClient([
            $this->json($this->emptyPayload()),
            $this->json(json_encode(['data' => [['id' => 271, 'name' => 'Superliga'], ['id' => 501, 'name' => 'Premiership']]], \JSON_THROW_ON_ERROR)),
        ]);

        $this->expectException(FeedPayloadInvalid::class);
        $this->expectExceptionMessage('league "8" is not covered by the current subscription (plans: Football Free Plan)');

        $this->provider($client)->fetchMatches($this->polledSource());
    }

    public function testEmptyAnswerWhileResultsAreOwedFails(): void
    {
        $client = new MockHttpClient([
            $this->json($this->emptyPayload()),
            $this->json(json_encode(['data' => [['id' => 8, 'name' => 'Premier League']]], \JSON_THROW_ON_ERROR)),
        ]);

        $this->expectException(FeedPayloadInvalid::class);
        $this->expectExceptionMessage('still awaiting a result');

        $this->provider($client)->fetchMatches($this->polledSource(), new \DateTimeImmutable('2026-08-05 15:00:00 UTC'));
    }

    /** A covered league with nothing owed may be empty: a winter break is real. */
    public function testEmptyAnswerForACoveredLeagueWithNothingOwedIsABreak(): void
    {
        $client = new MockHttpClient([
            $this->json($this->emptyPayload()),
            $this->json(json_encode(['data' => [['id' => 8, 'name' => 'Premier League']]], \JSON_THROW_ON_ERROR)),
        ]);

        self::assertSame([], $this->provider($client)->fetchMatches($this->polledSource()));
    }

    /**
     * @return list<string>
     */
    private function requestedUrls(?\DateTimeImmutable $awaitingResultSince): array
    {
        $urls = [];
        $client = new MockHttpClient(function (string $method, string $url) use (&$urls): MockResponse {
            $urls[] = $url;

            return $this->json($this->payload());
        });

        $this->provider($client)->fetchMatches($this->polledSource(), $awaitingResultSince);

        return $urls;
    }

    private function provider(MockHttpClient $client): SportmonksMatchDataProvider
    {
        return new SportmonksMatchDataProvider(
            $client,
            new MockClock(new \DateTimeImmutable('2026-08-08 12:00:00', new \DateTimeZone('UTC'))),
            'test-key',
        );
    }

    private function polledSource(): \App\Entity\MatchSource
    {
        $source = FeedSourceFactory::create(FeedProvider::Sportmonks, '8');
        $source->markFeedPolled(new \DateTimeImmutable('2026-08-08 11:00:00', new \DateTimeZone('UTC')));

        return $source;
    }

    private function json(string $body): MockResponse
    {
        return new MockResponse($body, ['response_headers' => ['content-type' => 'application/json']]);
    }

    private function emptyPayload(): string
    {
        return json_encode([
            'message' => "No result(s) found matching your request. Either the query did not return any results or you don't have access to it via your current subscription.",
            'subscription' => [['plans' => [['plan' => 'Football Free Plan', 'sport' => 'Football']]]],
        ], \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<\App\Service\Feed\MatchSnapshot>
     */
    private function fetch(): array
    {
        $client = new MockHttpClient([
            new MockResponse($this->payload(), ['response_headers' => ['content-type' => 'application/json']]),
        ]);

        $provider = new SportmonksMatchDataProvider(
            $client,
            new MockClock(new \DateTimeImmutable('2026-08-08 12:00:00', new \DateTimeZone('UTC'))),
            'test-key',
        );

        $source = FeedSourceFactory::create(FeedProvider::Sportmonks, '8');
        $source->markFeedPolled(new \DateTimeImmutable('2026-08-08 11:00:00', new \DateTimeZone('UTC')));

        return $provider->fetchMatches($source);
    }

    private function payload(): string
    {
        return json_encode([
            'data' => [
                [
                    'id' => 19722203,
                    'starting_at' => '2026-08-21 19:00:00',
                    'starting_at_timestamp' => 1787338800,
                    'state' => ['id' => 1, 'state' => 'NS', 'name' => 'Not Started'],
                    'participants' => [
                        ['id' => 117, 'name' => 'Coventry City', 'meta' => ['location' => 'away']],
                        ['id' => 19, 'name' => 'Arsenal', 'meta' => ['location' => 'home']],
                    ],
                    'scores' => [],
                    'events' => [],
                    'round' => ['id' => 407874, 'name' => '1'],
                    'venue' => ['id' => 204, 'name' => 'Emirates Stadium'],
                ],
                [
                    'id' => 19427236,
                    'starting_at' => '2026-05-24 15:00:00',
                    'starting_at_timestamp' => 1779980400,
                    'state' => ['id' => 5, 'state' => 'FT', 'name' => 'Full Time'],
                    'participants' => [
                        ['id' => 78, 'name' => 'Brighton & Hove Albion', 'meta' => ['location' => 'home']],
                        ['id' => 14, 'name' => 'Manchester United', 'meta' => ['location' => 'away']],
                    ],
                    'scores' => [
                        ['type_id' => 1525, 'participant_id' => 78, 'score' => ['goals' => 0, 'participant' => 'home'], 'description' => 'CURRENT'],
                        ['type_id' => 1525, 'participant_id' => 14, 'score' => ['goals' => 3, 'participant' => 'away'], 'description' => 'CURRENT'],
                        ['type_id' => 1, 'participant_id' => 14, 'score' => ['goals' => 2, 'participant' => 'away'], 'description' => '1ST_HALF'],
                    ],
                    'events' => [
                        ['type_id' => 14, 'minute' => 33, 'player_name' => 'Patrick Dorgu', 'participant_id' => 14],
                        ['type_id' => 16, 'minute' => 48, 'player_name' => 'Bruno Fernandes', 'participant_id' => 14],
                        ['type_id' => 15, 'minute' => 60, 'player_name' => 'Someone Unlucky', 'participant_id' => 78],
                        ['type_id' => 19, 'minute' => 70, 'player_name' => 'A Booked Player', 'participant_id' => 78],
                    ],
                    'round' => ['id' => 1, 'name' => '38'],
                    'venue' => ['id' => 1, 'name' => 'Amex Stadium'],
                ],
                [
                    'id' => 19427237,
                    'starting_at_timestamp' => 1786000000,
                    'state' => ['id' => 22, 'state' => 'INPLAY_2ND_HALF', 'name' => '2nd Half'],
                    'participants' => [
                        ['id' => 1, 'name' => 'Everton', 'meta' => ['location' => 'home']],
                        ['id' => 2, 'name' => 'Crystal Palace', 'meta' => ['location' => 'away']],
                    ],
                    'scores' => [
                        ['participant_id' => 1, 'score' => ['goals' => 1], 'description' => 'CURRENT'],
                        ['participant_id' => 2, 'score' => ['goals' => 1], 'description' => 'CURRENT'],
                    ],
                ],
                [
                    'id' => 19427238,
                    'starting_at_timestamp' => 1786000000,
                    'state' => ['id' => 15, 'state' => 'ABANDONED', 'name' => 'Abandoned'],
                    'participants' => [
                        ['id' => 3, 'name' => 'Leeds United', 'meta' => ['location' => 'home']],
                        ['id' => 4, 'name' => 'Sunderland', 'meta' => ['location' => 'away']],
                    ],
                    'scores' => [],
                ],
            ],
            'pagination' => ['count' => 4, 'per_page' => 50, 'has_more' => false, 'current_page' => 1],
        ], \JSON_THROW_ON_ERROR);
    }
}
