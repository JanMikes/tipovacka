<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Feed;

use App\Enum\FeedProvider;
use App\Service\Feed\MatchSnapshot;
use App\Service\Feed\UefaMatchDataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Real payload, real ties. `payload/uefa-overtime-2026-09-03.json` holds seven
 * rows trimmed (keys kept verbatim) from match.uefa.com/v5 on 2026-09-03, chosen
 * out of all 428 finished 2026/27 UCL+UEL+UECL rows to cover every shape that
 * decides whether a draw carries a winner — including the two that look like an
 * answer and are not. See .docs/MATCH_DATA_FEEDS.md §Adapter 2.
 */
final class UefaOvertimeWinnerTest extends TestCase
{
    /**
     * Pafos – Dinamo City, UECL play-off 2nd leg, 2026-08-27: `score.regular`
     * 2:2, `score.total` 4:2. The stored pair is 3:2 and NOT the 4:2 UEFA
     * publishes — the app records who won, never the prolongation's real score.
     */
    public function testExtraTimeWinnerBecomesTheDrawPlusOneGoal(): void
    {
        $snapshot = $this->snapshot('2049288');

        self::assertSame([2, 2], [$snapshot->homeScore, $snapshot->awayScore]);
        self::assertSame([3, 2], [$snapshot->overtimeHomeScore, $snapshot->overtimeAwayScore]);
    }

    /**
     * SK Rapid – Hearts, UECL play-off 2nd leg, 2026-08-26: regular 1:1, total
     * 2:2 (extra time settled nothing), `score.penalty` 3:4. The shootout is the
     * tie-breaker of last resort — note the SINGULAR key.
     */
    public function testShootoutWinnerIsReadWhenExtraTimeStayedLevel(): void
    {
        $snapshot = $this->snapshot('2049278');

        self::assertSame([1, 1], [$snapshot->homeScore, $snapshot->awayScore]);
        self::assertSame([1, 2], [$snapshot->overtimeHomeScore, $snapshot->overtimeAwayScore]);
    }

    /** CSKA Sofia – Qarabağ, UEL 2nd qualifying 2nd leg, 2026-07-30: 0:0, penalty 5:4. */
    public function testGoallessTieDecidedOnPenaltiesGivesTheHomeSide(): void
    {
        self::assertSame([1, 0], $this->overtimePair('2048750'));
    }

    /**
     * Thun – Lech Poznań, UEL play-off 2nd leg, 2026-08-27: 2:2 with the TIE at
     * 2:9. No extra time, no shootout — the second leg was a draw and the tie was
     * already decided. `score.aggregate` is never this match's score.
     */
    public function testSecondLegDrawSettledOnAggregateHasNoWinner(): void
    {
        self::assertSame([null, null], $this->overtimePair('2049242'));
    }

    /**
     * The trap that makes `winner.aggregate` unusable: Egnatia – Celje, UCL 2nd
     * qualifying FIRST leg, 2026-07-22, a plain 3:3 — whose row already carries
     * `winner.aggregate.reason = WIN_ON_PENALTIES` for a shootout taken five days
     * later, in the other leg. Reading it would invent a winner for a match that
     * drew.
     */
    public function testFirstLegDrawDoesNotInheritTheShootoutOfItsSecondLeg(): void
    {
        self::assertSame([null, null], $this->overtimePair('2048724'));
    }

    /**
     * Zrinjski – SK Rapid, UECL league phase MD6, 2025-12-18: 1:1 and nothing
     * else in the row. A league draw is simply a draw.
     */
    public function testLeaguePhaseDrawHasNoWinner(): void
    {
        self::assertSame([null, null], $this->overtimePair('2046402'));
    }

    /**
     * Jablonec – Rangers, UECL play-off 2nd leg, 2026-08-27: won 1:0 inside 90
     * minutes, with the TIE then settled 4:3 on penalties. A match with a winner
     * in regular time never gets an overtime pair — `score.penalty` alone means
     * nothing without a draw next to it.
     */
    public function testShootoutOnAMatchWonInRegularTimeIsIgnored(): void
    {
        self::assertSame([null, null], $this->overtimePair('2049286'));
    }

    /**
     * @return array{int|null, int|null}
     */
    private function overtimePair(string $externalId): array
    {
        $snapshot = $this->snapshot($externalId);

        return [$snapshot->overtimeHomeScore, $snapshot->overtimeAwayScore];
    }

    private function snapshot(string $externalId): MatchSnapshot
    {
        foreach ($this->fetch() as $snapshot) {
            if ($snapshot->externalId === $externalId) {
                return $snapshot;
            }
        }

        self::fail(sprintf('The payload holds no match "%s".', $externalId));
    }

    /**
     * @return list<MatchSnapshot>
     */
    private function fetch(): array
    {
        $payload = file_get_contents(__DIR__.'/payload/uefa-overtime-2026-09-03.json');
        self::assertIsString($payload);

        $client = new MockHttpClient([
            new MockResponse($payload, ['response_headers' => ['content-type' => 'application/json']]),
        ]);

        return (new UefaMatchDataProvider($client, new NullLogger()))->fetchMatches(
            FeedSourceFactory::create(
                FeedProvider::Uefa,
                '2019',
                startAt: new \DateTimeImmutable('2026-07-01'),
                endAt: new \DateTimeImmutable('2027-05-30'),
            ),
        );
    }
}
