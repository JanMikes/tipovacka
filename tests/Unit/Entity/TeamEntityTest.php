<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class TeamEntityTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    public function testGlobalDirectoryTeamHasNoSource(): void
    {
        $team = new Team(
            id: Uuid::v7(),
            sport: $this->football(),
            matchSource: null,
            name: 'Sparta Praha',
            createdAt: $this->now,
            shortName: 'SPA',
            country: 'CZ',
            brandColor: '#EE1C25',
        );

        self::assertTrue($team->isGlobal);
        self::assertFalse($team->isLocal);
        self::assertSame('Sparta Praha', $team->name);
        self::assertSame('SPA', $team->shortName);
        self::assertSame('CZ', $team->country);
        self::assertSame('#EE1C25', $team->brandColor);
        self::assertNull($team->logo);
        self::assertSame('SP', $team->monogram->initials);
        self::assertSame('#EE1C25', $team->monogram->background);
    }

    public function testLocalTeamCarriesItsSource(): void
    {
        $source = $this->privateSource();

        $team = new Team(
            id: Uuid::v7(),
            sport: $this->football(),
            matchSource: $source,
            name: 'Marketing',
            createdAt: $this->now,
        );

        self::assertFalse($team->isGlobal);
        self::assertTrue($team->isLocal);
        self::assertSame($source, $team->matchSource);
        self::assertNull($team->shortName);
        self::assertNull($team->brandColor);
    }

    public function testUpdateDetailsChangesFieldsAndTimestamp(): void
    {
        $team = new Team(
            id: Uuid::v7(),
            sport: $this->football(),
            matchSource: null,
            name: 'Sparta',
            createdAt: $this->now,
        );

        $later = $this->now->modify('+1 day');
        $team->updateDetails('Sparta Praha', 'SPA', 'CZ', '#EE1C25', $later);

        self::assertSame('Sparta Praha', $team->name);
        self::assertSame('SPA', $team->shortName);
        self::assertSame('CZ', $team->country);
        self::assertSame('#EE1C25', $team->brandColor);
        self::assertEquals($later, $team->updatedAt);
    }

    private function football(): Sport
    {
        return new Sport(
            id: Uuid::fromString(Sport::FOOTBALL_ID),
            code: 'football',
            name: 'Fotbal',
            periodCount: 2,
            periodLabelSingular: 'poločas',
            periodLabelPlural: 'poločasy',
        );
    }

    private function privateSource(): MatchSource
    {
        $owner = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: AppFixtures::VERIFIED_USER_EMAIL,
            password: 'hash',
            nickname: AppFixtures::VERIFIED_USER_NICKNAME,
            createdAt: $this->now,
        );
        $owner->popEvents();

        $source = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: $this->football(),
            owner: $owner,
            kind: MatchSourceKind::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $source->popEvents();

        return $source;
    }
}
