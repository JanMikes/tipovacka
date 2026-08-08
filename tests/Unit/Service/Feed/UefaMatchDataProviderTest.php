<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Feed;

use App\Enum\FeedMatchStatus;
use App\Enum\FeedProvider;
use App\Enum\MatchSide;
use App\Service\Feed\UefaMatchDataProvider;
use PHPUnit\Framework\TestCase;
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
     * @return list<\App\Service\Feed\MatchSnapshot>
     */
    private function fetch(): array
    {
        $client = new MockHttpClient([
            new MockResponse($this->payload(), ['response_headers' => ['content-type' => 'application/json']]),
        ]);

        return (new UefaMatchDataProvider($client))->fetchMatches(
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
                'homeTeam' => ['id' => '1', 'internationalName' => 'Lyon or Sparta Praha'],
                'awayTeam' => ['id' => '2', 'internationalName' => 'Fenerbahçe or Sturm Graz'],
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
                'id' => '2049169',
                'status' => 'FINISHED',
                'kickOffTime' => ['dateTime' => '2026-08-13T19:00:00Z'],
                'homeTeam' => ['id' => '5', 'internationalName' => 'Auda'],
                'awayTeam' => ['id' => '6', 'internationalName' => 'Dinamo City'],
                'score' => ['regular' => ['home' => 0, 'away' => 0]],
                'playerEvents' => ['scorers' => []],
            ],
        ], \JSON_THROW_ON_ERROR);
    }
}
