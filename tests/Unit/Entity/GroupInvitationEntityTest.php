<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\GroupInvitation;
use App\Entity\Sport;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentVisibility;
use App\Event\GroupInvitationAccepted;
use App\Event\GroupInvitationRevoked;
use App\Event\GroupInvitationSent;
use App\Exception\GroupInvitationAlreadyAccepted;
use App\Exception\GroupInvitationAlreadyRevoked;
use App\Exception\GroupInvitationExpired;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class GroupInvitationEntityTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    private function makeUser(string $suffix = 'a'): User
    {
        $user = new User(
            id: Uuid::v7(),
            email: 'u'.$suffix.'@test.com',
            password: 'hash',
            nickname: 'u'.$suffix,
            createdAt: $this->now,
        );
        $user->markAsVerified($this->now);
        $user->popEvents();

        return $user;
    }

    private function makeInvitation(?\DateTimeImmutable $expiresAt = null): GroupInvitation
    {
        $owner = $this->makeUser('owner');
        $inviter = $this->makeUser('inviter');

        $tournament = new Tournament(
            id: Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            visibility: TournamentVisibility::Public,
            name: 'Turnaj',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $tournament->popEvents();

        $group = new Group(
            id: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
            tournament: $tournament,
            owner: $owner,
            name: 'Skupina',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: $this->now,
        );
        $group->popEvents();

        return new GroupInvitation(
            id: Uuid::v7(),
            group: $group,
            inviter: $inviter,
            email: 'guest@tipovacka.test',
            token: str_repeat('a', 64),
            createdAt: $this->now,
            expiresAt: $expiresAt ?? $this->now->modify('+7 days'),
        );
    }

    public function testConstructorRecordsSentEvent(): void
    {
        $invitation = $this->makeInvitation();

        $events = $invitation->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(GroupInvitationSent::class, $events[0]);
        self::assertSame('guest@tipovacka.test', $events[0]->email);
    }

    public function testFreshInvitationIsNotAcceptedOrRevoked(): void
    {
        $invitation = $this->makeInvitation();

        self::assertFalse($invitation->isAccepted);
        self::assertFalse($invitation->isRevoked);
    }

    public function testIsExpiredAtFutureDate(): void
    {
        $invitation = $this->makeInvitation(expiresAt: $this->now->modify('+7 days'));

        self::assertFalse($invitation->isExpiredAt($this->now));
        self::assertFalse($invitation->isExpiredAt($this->now->modify('+6 days')));
        self::assertTrue($invitation->isExpiredAt($this->now->modify('+8 days')));
    }

    public function testAcceptRecordsEvent(): void
    {
        $invitation = $this->makeInvitation();
        $invitation->popEvents();

        $userId = Uuid::v7();
        $invitation->accept($userId, $this->now);

        self::assertTrue($invitation->isAccepted);
        self::assertSame($this->now, $invitation->acceptedAt);

        $events = $invitation->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(GroupInvitationAccepted::class, $events[0]);
        self::assertSame($userId, $events[0]->userId);
    }

    public function testCannotAcceptExpired(): void
    {
        $invitation = $this->makeInvitation(expiresAt: $this->now->modify('-1 day'));
        $invitation->popEvents();

        $this->expectException(GroupInvitationExpired::class);

        $invitation->accept(Uuid::v7(), $this->now);
    }

    public function testCannotAcceptTwice(): void
    {
        $invitation = $this->makeInvitation();
        $invitation->popEvents();
        $invitation->accept(Uuid::v7(), $this->now);
        $invitation->popEvents();

        $this->expectException(GroupInvitationAlreadyAccepted::class);

        $invitation->accept(Uuid::v7(), $this->now);
    }

    public function testCannotAcceptRevoked(): void
    {
        $invitation = $this->makeInvitation();
        $invitation->popEvents();
        $invitation->revoke($this->now);
        $invitation->popEvents();

        $this->expectException(GroupInvitationAlreadyRevoked::class);

        $invitation->accept(Uuid::v7(), $this->now);
    }

    public function testRevokeRecordsEvent(): void
    {
        $invitation = $this->makeInvitation();
        $invitation->popEvents();

        $invitation->revoke($this->now);

        self::assertTrue($invitation->isRevoked);

        $events = $invitation->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(GroupInvitationRevoked::class, $events[0]);
    }

    public function testRevokeTwiceIsIdempotent(): void
    {
        $invitation = $this->makeInvitation();
        $invitation->popEvents();
        $invitation->revoke($this->now);
        $invitation->popEvents();

        $invitation->revoke($this->now);

        self::assertCount(0, $invitation->popEvents());
    }

    public function testCannotRevokeAcceptedInvitation(): void
    {
        $invitation = $this->makeInvitation();
        $invitation->popEvents();
        $invitation->accept(Uuid::v7(), $this->now);
        $invitation->popEvents();

        $this->expectException(GroupInvitationAlreadyAccepted::class);

        $invitation->revoke($this->now);
    }
}
