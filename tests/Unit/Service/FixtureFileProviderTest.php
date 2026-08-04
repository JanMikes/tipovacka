<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\FeedMatchStatus;
use App\Enum\FeedProvider;
use App\Enum\MatchEventType;
use App\Enum\MatchSide;
use App\Enum\MatchSourceKind;
use App\Exception\FeedPayloadInvalid;
use App\Service\Feed\FixtureFileProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class FixtureFileProviderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/feed-fixture-'.bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
    }

    public function testParsesSnapshotsFromJsonFile(): void
    {
        $path = $this->write([
            [
                'externalId' => '8327',
                'homeTeam' => 'Sparta Praha',
                'awayTeam' => 'Slavia Praha',
                'kickoffUtc' => '2025-08-01T17:00:00+02:00',
                'status' => 'finished',
                'homeScore' => 2,
                'awayScore' => 1,
                'periodScores' => [[1, 0], [1, 1]],
                'events' => [
                    ['type' => 'goal', 'side' => 'home', 'minute' => 51, 'player' => 'Karel Novák'],
                    ['type' => 'yellow_card', 'side' => 'away', 'player' => 'Petr Malý'],
                ],
                'round' => '1. kolo',
                'venue' => 'epet ARENA',
            ],
            [
                'externalId' => '8328',
                'homeTeam' => 'Baník Ostrava',
                'awayTeam' => 'Jablonec',
                'kickoffUtc' => '2025-08-02T15:00:00Z',
                'status' => 'weird-vendor-code',
            ],
        ]);

        $snapshots = $this->provider()->fetchMatches($this->source($path));

        self::assertCount(2, $snapshots);

        $first = $snapshots[0];
        self::assertSame('8327', $first->externalId);
        self::assertSame(FeedMatchStatus::Finished, $first->status);
        // Offset input is converted to UTC.
        self::assertSame('2025-08-01 15:00:00', $first->kickoffUtc->format('Y-m-d H:i:s'));
        self::assertSame([[1, 0], [1, 1]], $first->periodScores);
        self::assertNotNull($first->events);
        self::assertCount(2, $first->events);
        self::assertSame(MatchEventType::Goal, $first->events[0]->type);
        self::assertSame(MatchSide::Home, $first->events[0]->side);
        self::assertSame(51, $first->events[0]->minute);
        self::assertSame('Karel Novák', $first->events[0]->playerName);
        self::assertNull($first->events[1]->minute);

        $second = $snapshots[1];
        self::assertSame(FeedMatchStatus::Unknown, $second->status);
        self::assertSame('weird-vendor-code', $second->rawStatus);
        self::assertNull($second->homeScore);
        self::assertNull($second->events);
    }

    public function testRejectsRowWithoutExternalId(): void
    {
        $path = $this->write([
            ['homeTeam' => 'A', 'awayTeam' => 'B', 'kickoffUtc' => '2025-08-01T17:00:00Z', 'status' => 'scheduled'],
        ]);

        $this->expectException(FeedPayloadInvalid::class);
        $this->provider()->fetchMatches($this->source($path));
    }

    public function testRejectsMissingFile(): void
    {
        $this->expectException(FeedPayloadInvalid::class);
        $this->provider()->fetchMatches($this->source($this->dir.'/missing.json'));
    }

    public function testRejectsNonListJson(): void
    {
        $path = $this->dir.'/object.json';
        file_put_contents($path, '{"not": "a list"}');

        $this->expectException(FeedPayloadInvalid::class);
        $this->provider()->fetchMatches($this->source($path));
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function write(array $rows): string
    {
        $path = $this->dir.'/feed.json';
        file_put_contents($path, json_encode($rows, JSON_THROW_ON_ERROR));

        return $path;
    }

    private function provider(): FixtureFileProvider
    {
        return new FixtureFileProvider(projectDir: $this->dir);
    }

    private function source(string $feedRef): MatchSource
    {
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $owner = new User(
            id: Uuid::fromString(AppFixtures::ADMIN_ID),
            email: 'a@t.cz',
            password: null,
            nickname: 'a',
            createdAt: $now,
        );
        $owner->popEvents();

        $sport = new Sport(
            id: Uuid::fromString(Sport::FOOTBALL_ID),
            code: 'football',
            name: 'Fotbal',
            periodCount: 2,
            periodLabelSingular: 'poločas',
            periodLabelPlural: 'poločasy',
        );

        $source = new MatchSource(
            id: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            sport: $sport,
            owner: $owner,
            kind: MatchSourceKind::Curated,
            name: 'Feed test source',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $now,
        );
        $source->popEvents();
        $source->bindFeed(FeedProvider::Fixture, $feedRef, $now);
        $source->popEvents();

        return $source;
    }
}
