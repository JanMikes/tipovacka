<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\Membership;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\MatchSourceKind;
use App\Event\MemberJoinedCompetition;
use App\Event\MemberLeftCompetition;
use App\Event\MemberRemoved;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MembershipEntityTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    private function makeUser(string $id): User
    {
        $user = new User(
            id: Uuid::fromString($id),
            email: 'u'.substr($id, -3).'@test.com',
            password: 'hash',
            nickname: 'u'.substr($id, -3),
            createdAt: $this->now,
        );
        $user->popEvents();

        return $user;
    }

    private function makeCompetition(User $owner): Competition
    {
        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            kind: MatchSourceKind::Private,
            name: 'Turnaj',
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
            name: 'Soutěž',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );
        $competition->popEvents();

        return $competition;
    }

    private function makeMembership(?User $user = null): Membership
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);

        return new Membership(
            id: Uuid::fromString('01933333-0000-7000-8000-0000000000aa'),
            competition: $this->makeCompetition($owner),
            user: $user ?? $owner,
            joinedAt: $this->now,
        );
    }

    public function testConstructorRecordsMemberJoinedEvent(): void
    {
        $membership = $this->makeMembership();

        $events = $membership->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberJoinedCompetition::class, $events[0]);
        self::assertTrue($membership->isActive);
    }

    public function testLeaveMarksInactive(): void
    {
        $membership = $this->makeMembership();
        $membership->popEvents();

        $later = new \DateTimeImmutable('2025-06-20 09:00:00 UTC');
        $membership->leave($later);

        self::assertFalse($membership->isActive);
        self::assertSame($later, $membership->leftAt);

        $events = $membership->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberLeftCompetition::class, $events[0]);
    }

    public function testLeaveIsIdempotent(): void
    {
        $membership = $this->makeMembership();
        $membership->popEvents();

        $first = new \DateTimeImmutable('2025-06-20 09:00:00 UTC');
        $membership->leave($first);
        $membership->popEvents();

        $membership->leave(new \DateTimeImmutable('2025-06-21 09:00:00 UTC'));

        self::assertSame($first, $membership->leftAt);
        self::assertCount(0, $membership->popEvents());
    }

    public function testRemoveByRecordsMemberRemovedEvent(): void
    {
        $owner = $this->makeUser(AppFixtures::VERIFIED_USER_ID);
        $target = $this->makeUser('01933333-0000-7000-8000-000000000011');

        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            kind: MatchSourceKind::Private,
            name: 'Turnaj',
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
            name: 'Soutěž',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );
        $competition->popEvents();

        $membership = new Membership(
            id: Uuid::fromString('01933333-0000-7000-8000-0000000000aa'),
            competition: $competition,
            user: $target,
            joinedAt: $this->now,
        );
        $membership->popEvents();

        $later = new \DateTimeImmutable('2025-06-20 09:00:00 UTC');
        $membership->removeBy($owner->id, $later);

        self::assertFalse($membership->isActive);
        self::assertSame($later, $membership->leftAt);

        $events = $membership->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MemberRemoved::class, $events[0]);
        self::assertSame($owner->id, $events[0]->removedByUserId);
    }
}
