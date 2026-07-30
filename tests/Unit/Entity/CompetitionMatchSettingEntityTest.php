<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionMatchSetting;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CompetitionMatchSettingEntityTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    public function testConstructionInitializesUpdatedAtFromCreatedAt(): void
    {
        $deadline = new \DateTimeImmutable('2025-06-20 17:00');
        $setting = $this->makeSetting($deadline);

        self::assertSame($deadline, $setting->deadline);
        self::assertSame($this->now, $setting->updatedAt);
    }

    public function testUpdateWindowChangesValueAndUpdatedAt(): void
    {
        $setting = $this->makeSetting(new \DateTimeImmutable('2025-06-20 17:00'));
        $later = new \DateTimeImmutable('2025-06-15 13:00:00 UTC');
        $newDeadline = new \DateTimeImmutable('2025-06-20 16:00');

        $setting->updateWindow($newDeadline, null, null, $later);

        self::assertSame($newDeadline, $setting->deadline);
        self::assertSame($later, $setting->updatedAt);
    }

    public function testWindowCarriesOpeningAndNote(): void
    {
        $opensAt = new \DateTimeImmutable('2025-06-18 09:00');
        $setting = $this->makeSetting(null, $opensAt, 'Tipy otevřeme po losu skupin.');

        self::assertNull($setting->deadline);
        self::assertSame($opensAt, $setting->opensAt);
        self::assertSame('Tipy otevřeme po losu skupin.', $setting->openingNote);
        self::assertFalse($setting->isEmpty);
    }

    public function testUpdateWindowFullyReplacesBothEnds(): void
    {
        $setting = $this->makeSetting(
            new \DateTimeImmutable('2025-06-20 17:00'),
            new \DateTimeImmutable('2025-06-18 09:00'),
            'Ještě čekáme na los.',
        );

        $setting->updateWindow(null, null, null, $this->now);

        self::assertNull($setting->deadline);
        self::assertNull($setting->opensAt);
        self::assertNull($setting->openingNote);
        self::assertTrue($setting->isEmpty);
    }

    public function testBlankNoteIsStoredAsNull(): void
    {
        $setting = $this->makeSetting(null, new \DateTimeImmutable('2025-06-18 09:00'), '   ');

        self::assertNull($setting->openingNote);

        $setting->updateWindow(null, new \DateTimeImmutable('2025-06-18 09:00'), "  Po losu.\n", $this->now);

        self::assertSame('Po losu.', $setting->openingNote);
    }

    public function testEmptyRowIsRecognizable(): void
    {
        $setting = $this->makeSetting(null);

        self::assertTrue($setting->isEmpty);
    }

    private function makeSetting(
        ?\DateTimeImmutable $deadline,
        ?\DateTimeImmutable $opensAt = null,
        ?string $openingNote = null,
    ): CompetitionMatchSetting {
        $owner = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: 'o@test.com',
            password: 'h',
            nickname: 'o',
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

        $competition = new Competition(
            id: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            matchSource: $matchSource,
            owner: $owner,
            name: 'G',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );
        $competition->popEvents();

        $match = new SportMatch(
            id: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            matchSource: $matchSource,
            homeTeam: new Team(id: Uuid::v7(), sport: $matchSource->sport, matchSource: $matchSource, name: 'A', createdAt: $this->now),
            awayTeam: new Team(id: Uuid::v7(), sport: $matchSource->sport, matchSource: $matchSource, name: 'B', createdAt: $this->now),
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00'),
            venue: null,
            createdAt: $this->now,
        );
        $match->popEvents();

        return new CompetitionMatchSetting(
            id: Uuid::v7(),
            competition: $competition,
            sportMatch: $match,
            deadline: $deadline,
            createdAt: $this->now,
            opensAt: $opensAt,
            openingNote: $openingNote,
        );
    }
}
