<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Feed;

use App\Enum\FeedMatchStatus;
use App\Enum\FeedProvider;
use App\Enum\MatchSide;
use App\Exception\FeedPayloadInvalid;
use App\Service\Feed\UefaMatchDataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Locks the shape of match.uefa.com/v5. The payload below is trimmed from a real
 * Konferenční liga response (fetched 2026-08-08): same nesting, same key names —
 * notably the stadium name living ONLY under `translations.name.EN` and the
 * scorer's side being a `teamId` that must be compared with `homeTeam.id`.
 */
final class UefaMatchDataProviderTest extends TestCase
{
    public function testParsesFinishedMatchWithScorers(): void
    {
        $snapshots = $this->fetch();

        $finished = $snapshots[0];
        self::assertSame('2049167', $finished->externalId);
        self::assertSame('Hibernian', $finished->homeTeamName);
        self::assertSame('Shkëndija', $finished->awayTeamName);
        self::assertSame(FeedMatchStatus::Finished, $finished->status);
        self::assertSame(2, $finished->homeScore);
        self::assertSame(1, $finished->awayScore);
        self::assertSame('Easter Road Stadium', $finished->venue);
        self::assertSame('3. předkolo – 2. zápas', $finished->round, 'round labels match the hand-seeded Czech ones');

        self::assertNotNull($finished->events);
        self::assertCount(2, $finished->events, 'the own goal is excluded — it scores no scorer tip');
        self::assertSame('Owen Elding', $finished->events[0]->playerName);
        self::assertSame(MatchSide::Home, $finished->events[0]->side);
        self::assertSame(74, $finished->events[0]->minute);
        self::assertSame(MatchSide::Away, $finished->events[1]->side);
    }

    public function testUpcomingMatchCarriesNoScoreAndNoEventSheet(): void
    {
        $upcoming = $this->fetch()[1];

        self::assertSame(FeedMatchStatus::Scheduled, $upcoming->status);
        self::assertNull($upcoming->homeScore);
        self::assertNull($upcoming->events, 'a null sheet never overwrites stored events');
        self::assertSame('2026-08-26 19:00:00', $upcoming->kickoffUtc->format('Y-m-d H:i:s'));
    }

    /**
     * UEFA publishes the score before the scorers. An empty sheet on a 2:0 would
     * otherwise be read as „verified: nobody scored" and delete manual entries.
     */
    public function testGoalsWithoutPublishedScorersLeaveTheSheetUnknown(): void
    {
        $snapshots = $this->fetch();

        self::assertNull($snapshots[2]->events);
        self::assertSame(2, $snapshots[2]->homeScore);
    }

    public function testGenuineGoallessDrawKeepsItsEmptySheet(): void
    {
        $snapshots = $this->fetch();

        self::assertSame([], $snapshots[3]->events);
    }

    /**
     * UEFA publishes knockout fixtures before their draw with participants like
     * „Lyon or Sparta Praha". Importing those is impossible and reporting them
     * as unpairable teams on every poll would bury the aliases that matter.
     */
    public function testUndrawnTiesAreSkippedEntirely(): void
    {
        $externalIds = array_map(static fn ($snapshot): string => $snapshot->externalId, $this->fetch());

        self::assertNotContains('2049300', $externalIds);
        self::assertCount(4, $externalIds);
    }

    /**
     * Mid-draw, UEFA briefly serves rows whose team objects have neither a name
     * nor the isPlaceHolder flag. On 2026-08-12 one such row failed the whole
     * Liga mistrů sync every five minutes for 3.5 hours — it must be skipped
     * like an undrawn tie, not abort the other ninety rows.
     */
    public function testRowWhoseTeamsHaveNoPublishedNameIsSkippedNotFatal(): void
    {
        $externalIds = array_map(static fn ($snapshot): string => $snapshot->externalId, $this->fetch());

        self::assertNotContains('2049400', $externalIds);
        self::assertCount(4, $externalIds, 'the rows around the nameless one still import');
    }

    /**
     * The other half of the same instalment: on 2026-08-13 UEFA published freshly
     * drawn Evropská liga ties with their team names but no kickOffTime.dateTime,
     * and row #0 failed the whole source every five minutes for 3.5 hours.
     */
    public function testRowWithoutPublishedKickoffIsSkippedNotFatal(): void
    {
        $externalIds = array_map(static fn ($snapshot): string => $snapshot->externalId, $this->fetch());

        self::assertNotContains('2049500', $externalIds);
        self::assertCount(4, $externalIds, 'the rows around the kickoff-less one still import');
    }

    /**
     * EVERY row unreadable is no draw window — the payload shape changed, and a
     * silently empty result would read as „nothing changed" on every poll.
     */
    public function testAllRowsNamelessFailsTheSourceLoudly(): void
    {
        $payload = json_encode([
            [
                'id' => '1',
                'status' => 'UPCOMING',
                'kickOffTime' => ['dateTime' => '2026-08-26T19:00:00Z'],
                'homeTeam' => ['id' => '7'],
                'awayTeam' => ['id' => '8'],
            ],
            [
                'id' => '2',
                'status' => 'UPCOMING',
                'kickOffTime' => ['dateTime' => '2026-08-26T19:00:00Z'],
                'homeTeam' => ['id' => '9'],
                'awayTeam' => ['id' => '10'],
            ],
        ], \JSON_THROW_ON_ERROR);

        $this->expectException(FeedPayloadInvalid::class);
        $this->expectExceptionMessage('no usable rows');

        $this->fetch($payload);
    }

    /**
     * @return list<\App\Service\Feed\MatchSnapshot>
     */
    private function fetch(?string $payload = null): array
    {
        $client = new MockHttpClient([
            new MockResponse($payload ?? $this->payload(), ['response_headers' => ['content-type' => 'application/json']]),
        ]);

        return (new UefaMatchDataProvider($client, new NullLogger()))->fetchMatches(
            FeedSourceFactory::create(
                FeedProvider::Uefa,
                '2019',
                startAt: new \DateTimeImmutable('2026-08-01'),
                endAt: new \DateTimeImmutable('2027-05-30'),
            ),
        );
    }

    private function payload(): string
    {
        return json_encode([
            [
                'id' => '2049167',
                'status' => 'FINISHED',
                'kickOffTime' => ['dateTime' => '2026-08-13T19:00:00Z'],
                'homeTeam' => ['id' => '52821', 'internationalName' => 'Hibernian'],
                'awayTeam' => ['id' => '61111', 'internationalName' => 'Shkëndija'],
                'score' => ['regular' => ['home' => 2, 'away' => 1], 'total' => ['home' => 2, 'away' => 1]],
                'round' => ['metaData' => ['name' => 'Third qualifying round', 'type' => 'THIRD_QUALIFYING']],
                'leg' => ['number' => 2],
                'stadium' => ['translations' => ['name' => ['EN' => 'Easter Road Stadium']]],
                'playerEvents' => ['scorers' => [
                    ['goalType' => 'SCORED', 'teamId' => '52821', 'time' => ['minute' => 74], 'player' => ['internationalName' => 'Owen Elding']],
                    ['goalType' => 'PENALTY', 'teamId' => '61111', 'time' => ['minute' => 88], 'player' => ['internationalName' => 'Besart Ibraimi']],
                    ['goalType' => 'OWN', 'teamId' => '61111', 'time' => ['minute' => 30], 'player' => ['internationalName' => 'Vlastní Gól']],
                ]],
            ],
            [
                'id' => '2049220',
                'status' => 'UPCOMING',
                'kickOffTime' => ['dateTime' => '2026-08-26T19:00:00Z'],
                'homeTeam' => ['id' => '1', 'internationalName' => 'Sparta Praha'],
                'awayTeam' => ['id' => '2', 'internationalName' => 'Sturm Graz'],
                'score' => null,
                'round' => ['metaData' => ['name' => 'Play-off', 'type' => 'PLAY_OFF']],
                'leg' => ['number' => 1],
            ],
            [
                'id' => '2049168',
                'status' => 'FINISHED',
                'kickOffTime' => ['dateTime' => '2026-08-13T19:00:00Z'],
                'homeTeam' => ['id' => '3', 'internationalName' => 'Rijeka'],
                'awayTeam' => ['id' => '4', 'internationalName' => 'Shelbourne'],
                'score' => ['regular' => ['home' => 2, 'away' => 0]],
                'playerEvents' => ['scorers' => []],
            ],
            [
                'id' => '2049300',
                'status' => 'UPCOMING',
                'kickOffTime' => ['dateTime' => '2026-08-26T19:00:00Z'],
                'homeTeam' => ['id' => '7', 'internationalName' => 'Lyon or Sparta Praha', 'isPlaceHolder' => true],
                'awayTeam' => ['id' => '8', 'internationalName' => 'Fenerbahçe or Sturm Graz', 'isPlaceHolder' => true],
                'score' => null,
            ],
            [
                'id' => '2049169',
                'status' => 'FINISHED',
                'kickOffTime' => ['dateTime' => '2026-08-13T19:00:00Z'],
                'homeTeam' => ['id' => '5', 'internationalName' => 'Auda'],
                'awayTeam' => ['id' => '6', 'internationalName' => 'Dinamo City'],
                'score' => ['regular' => ['home' => 0, 'away' => 0]],
                'playerEvents' => ['scorers' => []],
            ],
            [
                // A drawn tie whose team names UEFA has not published yet:
                // no name keys, isPlaceHolder false (the 2026-08-12 shape).
                'id' => '2049400',
                'status' => 'UPCOMING',
                'kickOffTime' => ['dateTime' => '2026-09-16T19:00:00Z'],
                'homeTeam' => ['id' => '11', 'isPlaceHolder' => false],
                'awayTeam' => ['id' => '12', 'isPlaceHolder' => false],
                'score' => null,
            ],
            [
                // A drawn tie whose kickoff UEFA has not scheduled yet: real
                // team names, a kickOffTime carrying the date alone (the
                // 2026-08-13 shape).
                'id' => '2049500',
                'status' => 'UPCOMING',
                'kickOffTime' => ['date' => '2026-09-24'],
                'homeTeam' => ['id' => '13', 'internationalName' => 'Ferencváros'],
                'awayTeam' => ['id' => '14', 'internationalName' => 'Malmö'],
                'score' => null,
            ],
        ], \JSON_THROW_ON_ERROR);
    }
}
