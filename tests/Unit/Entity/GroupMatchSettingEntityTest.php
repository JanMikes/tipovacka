<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\GroupMatchSetting;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentVisibility;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class GroupMatchSettingEntityTest extends TestCase
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

    public function testUpdateDeadlineChangesValueAndUpdatedAt(): void
    {
        $setting = $this->makeSetting(new \DateTimeImmutable('2025-06-20 17:00'));
        $later = new \DateTimeImmutable('2025-06-15 13:00:00 UTC');
        $newDeadline = new \DateTimeImmutable('2025-06-20 16:00');

        $setting->updateDeadline($newDeadline, $later);

        self::assertSame($newDeadline, $setting->deadline);
        self::assertSame($later, $setting->updatedAt);
    }

    private function makeSetting(\DateTimeImmutable $deadline): GroupMatchSetting
    {
        $owner = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: 'o@test.com',
            password: 'h',
            nickname: 'o',
            createdAt: $this->now,
        );
        $owner->popEvents();

        $tournament = new Tournament(
            id: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            visibility: TournamentVisibility::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $tournament->popEvents();

        $group = new Group(
            id: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            tournament: $tournament,
            owner: $owner,
            name: 'G',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );
        $group->popEvents();

        $match = new SportMatch(
            id: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            tournament: $tournament,
            homeTeam: 'A',
            awayTeam: 'B',
            kickoffAt: new \DateTimeImmutable('2025-06-20 18:00'),
            venue: null,
            createdAt: $this->now,
        );
        $match->popEvents();

        return new GroupMatchSetting(
            id: Uuid::v7(),
            group: $group,
            sportMatch: $match,
            deadline: $deadline,
            createdAt: $this->now,
        );
    }
}
