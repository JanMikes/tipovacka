<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\Sport;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentVisibility;
use App\Event\GroupCreated;
use App\Event\GroupDeleted;
use App\Event\GroupPinRegenerated;
use App\Event\GroupPinRevoked;
use App\Event\GroupShareableLinkRegenerated;
use App\Event\GroupShareableLinkRevoked;
use App\Event\GroupUpdated;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class GroupEntityTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
    }

    private function makeOwner(): User
    {
        $owner = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: AppFixtures::VERIFIED_USER_EMAIL,
            password: 'hash',
            nickname: AppFixtures::VERIFIED_USER_NICKNAME,
            createdAt: $this->now,
        );
        $owner->popEvents();

        return $owner;
    }

    private function makeTournament(User $owner): Tournament
    {
        $tournament = new Tournament(
            id: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal'),
            owner: $owner,
            visibility: TournamentVisibility::Private,
            name: 'Turnaj',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $tournament->popEvents();

        return $tournament;
    }

    private function makeGroup(?string $pin = null, ?string $token = 'token-x'): Group
    {
        $owner = $this->makeOwner();

        return new Group(
            id: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            tournament: $this->makeTournament($owner),
            owner: $owner,
            name: 'Skupina',
            description: null,
            pin: $pin,
            shareableLinkToken: $token,
            createdAt: $this->now,
        );
    }

    public function testConstructorRecordsCreatedEvent(): void
    {
        $group = $this->makeGroup();

        $events = $group->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(GroupCreated::class, $events[0]);
        self::assertSame($group->id, $events[0]->groupId);
    }

    public function testIsNotDeletedWhenFresh(): void
    {
        $group = $this->makeGroup();

        self::assertTrue($group->isNotDeleted);
        self::assertFalse($group->isDeleted());
    }

    public function testTipVisibilityDefaultsOnFreshGroup(): void
    {
        $group = $this->makeGroup();

        self::assertFalse($group->hideOthersTipsBeforeDeadline);
        self::assertNull($group->tipsDeadline);
    }

    public function testUpdateDetailsRecordsEvent(): void
    {
        $group = $this->makeGroup();
        $group->popEvents();

        $later = new \DateTimeImmutable('2025-06-16 12:00:00 UTC');
        $group->updateDetails(
            name: 'Nový',
            description: 'Popis',
            hideOthersTipsBeforeDeadline: false,
            tipsDeadline: null,
            now: $later,
        );

        self::assertSame('Nový', $group->name);
        self::assertSame('Popis', $group->description);
        self::assertSame($later, $group->updatedAt);

        $events = $group->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(GroupUpdated::class, $events[0]);
    }

    public function testUpdateDetailsAppliesNewTipVisibilityFields(): void
    {
        $group = $this->makeGroup();
        $group->popEvents();

        $deadline = new \DateTimeImmutable('2025-06-19 09:00:00 UTC');
        $group->updateDetails(
            name: $group->name,
            description: $group->description,
            hideOthersTipsBeforeDeadline: true,
            tipsDeadline: $deadline,
            now: $this->now,
        );

        self::assertTrue($group->hideOthersTipsBeforeDeadline);
        self::assertSame($deadline, $group->tipsDeadline);
    }

    public function testUpdateDetailsCanClearTipsDeadline(): void
    {
        $group = $this->makeGroup();
        $deadline = new \DateTimeImmutable('2025-06-19 09:00:00 UTC');
        $group->updateDetails(
            name: $group->name,
            description: $group->description,
            hideOthersTipsBeforeDeadline: true,
            tipsDeadline: $deadline,
            now: $this->now,
        );

        $group->updateDetails(
            name: $group->name,
            description: $group->description,
            hideOthersTipsBeforeDeadline: false,
            tipsDeadline: null,
            now: $this->now,
        );

        self::assertFalse($group->hideOthersTipsBeforeDeadline);
        self::assertNull($group->tipsDeadline);
    }

    public function testSetPinRecordsEvent(): void
    {
        $group = $this->makeGroup();
        $group->popEvents();

        $group->setPin('12345678', $this->now);

        self::assertSame('12345678', $group->pin);

        $events = $group->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(GroupPinRegenerated::class, $events[0]);
    }

    public function testRevokePinRecordsEventOnlyWhenPresent(): void
    {
        $group = $this->makeGroup(pin: '12345678');
        $group->popEvents();

        $group->revokePin($this->now);
        self::assertNull($group->pin);

        $events = $group->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(GroupPinRevoked::class, $events[0]);

        // Second revoke is a no-op
        $group->revokePin($this->now);
        self::assertCount(0, $group->popEvents());
    }

    public function testSetShareableLinkTokenRecordsEvent(): void
    {
        $group = $this->makeGroup();
        $group->popEvents();

        $group->setShareableLinkToken('new-token', $this->now);

        self::assertSame('new-token', $group->shareableLinkToken);

        $events = $group->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(GroupShareableLinkRegenerated::class, $events[0]);
    }

    public function testRevokeShareableLinkTokenRecordsEventOnlyWhenPresent(): void
    {
        $group = $this->makeGroup(token: 'token-x');
        $group->popEvents();

        $group->revokeShareableLinkToken($this->now);
        self::assertNull($group->shareableLinkToken);

        $events = $group->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(GroupShareableLinkRevoked::class, $events[0]);

        $group->revokeShareableLinkToken($this->now);
        self::assertCount(0, $group->popEvents());
    }

    public function testSoftDeleteRecordsEvent(): void
    {
        $group = $this->makeGroup();
        $group->popEvents();

        $group->softDelete($this->now);

        self::assertTrue($group->isDeleted());
        self::assertFalse($group->isNotDeleted);

        $events = $group->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(GroupDeleted::class, $events[0]);
    }

    public function testSoftDeleteIsIdempotent(): void
    {
        $group = $this->makeGroup();
        $firstDelete = new \DateTimeImmutable('2025-06-16 09:00:00 UTC');
        $group->softDelete($firstDelete);
        $group->popEvents();

        $group->softDelete(new \DateTimeImmutable('2025-06-17 09:00:00 UTC'));

        self::assertSame($firstDelete, $group->deletedAt);
        self::assertCount(0, $group->popEvents());
    }
}
