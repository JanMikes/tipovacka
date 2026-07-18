<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchEvent;
use App\Entity\MatchSource;
use App\Entity\Player;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\MatchEventType;
use App\Enum\MatchSide;
use App\Enum\MatchSourceKind;
use App\Exception\InvalidMatchEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MatchEventEntityTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    private function makeMatch(): SportMatch
    {
        $owner = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: AppFixtures::VERIFIED_USER_EMAIL,
            password: 'hash',
            nickname: AppFixtures::VERIFIED_USER_NICKNAME,
            createdAt: $this->now,
        );
        $owner->popEvents();

        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal', 2, 'poločas', 'poločasy'),
            owner: $owner,
            kind: MatchSourceKind::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $matchSource->popEvents();

        $match = new SportMatch(
            id: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            matchSource: $matchSource,
            homeTeam: 'A',
            awayTeam: 'B',
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00:00 UTC'),
            venue: null,
            createdAt: $this->now,
        );
        $match->popEvents();

        return $match;
    }

    private function makePlayer(SportMatch $match): Player
    {
        return new Player(
            id: Uuid::v7(),
            matchSource: $match->matchSource,
            teamName: $match->homeTeam,
            name: 'Jan Novák',
            createdAt: $this->now,
        );
    }

    public function testConstructsGoalEvent(): void
    {
        $match = $this->makeMatch();
        $player = $this->makePlayer($match);

        $event = new MatchEvent(
            id: Uuid::v7(),
            sportMatch: $match,
            type: MatchEventType::Goal,
            side: MatchSide::Home,
            minute: 27,
            player: $player,
            createdAt: $this->now,
        );

        self::assertSame(MatchEventType::Goal, $event->type);
        self::assertSame(MatchSide::Home, $event->side);
        self::assertSame(27, $event->minute);
        self::assertSame($player, $event->player);
        self::assertSame($match, $event->sportMatch);
    }

    public function testMinuteMayBeNullAndBoundariesAreAllowed(): void
    {
        $match = $this->makeMatch();
        $player = $this->makePlayer($match);

        foreach ([null, 0, 150] as $minute) {
            $event = new MatchEvent(
                id: Uuid::v7(),
                sportMatch: $match,
                type: MatchEventType::YellowCard,
                side: MatchSide::Away,
                minute: $minute,
                player: $player,
                createdAt: $this->now,
            );
            self::assertSame($minute, $event->minute);
        }
    }

    public function testNegativeMinuteIsRejected(): void
    {
        $match = $this->makeMatch();

        $this->expectException(InvalidMatchEvent::class);
        new MatchEvent(
            id: Uuid::v7(),
            sportMatch: $match,
            type: MatchEventType::Goal,
            side: MatchSide::Home,
            minute: -1,
            player: $this->makePlayer($match),
            createdAt: $this->now,
        );
    }

    public function testMinuteAbove150IsRejected(): void
    {
        $match = $this->makeMatch();

        $this->expectException(InvalidMatchEvent::class);
        new MatchEvent(
            id: Uuid::v7(),
            sportMatch: $match,
            type: MatchEventType::RedCard,
            side: MatchSide::Away,
            minute: 151,
            player: $this->makePlayer($match),
            createdAt: $this->now,
        );
    }

    public function testEnumBackingValues(): void
    {
        // Backing values are persisted in match_events — guard them from accidental renames.
        $expectedTypes = ['goal' => MatchEventType::Goal, 'yellow_card' => MatchEventType::YellowCard, 'red_card' => MatchEventType::RedCard];
        foreach ($expectedTypes as $value => $case) {
            self::assertSame($case, MatchEventType::from($value));
        }
        self::assertSameSize($expectedTypes, MatchEventType::cases());

        $expectedSides = ['home' => MatchSide::Home, 'away' => MatchSide::Away];
        foreach ($expectedSides as $value => $case) {
            self::assertSame($case, MatchSide::from($value));
        }
        self::assertSameSize($expectedSides, MatchSide::cases());
    }
}
