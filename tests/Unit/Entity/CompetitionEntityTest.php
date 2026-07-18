<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\MatchSourceKind;
use App\Event\CompetitionCreated;
use App\Event\CompetitionDeleted;
use App\Event\CompetitionMatchSelectionChanged;
use App\Event\CompetitionPinRegenerated;
use App\Event\CompetitionPinRevoked;
use App\Event\CompetitionShareableLinkRegenerated;
use App\Event\CompetitionShareableLinkRevoked;
use App\Event\CompetitionUpdated;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CompetitionEntityTest extends TestCase
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

    private function makeMatchSource(User $owner): MatchSource
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

        return $matchSource;
    }

    private function makeCompetition(?string $pin = null, ?string $token = 'token-x'): Competition
    {
        $owner = $this->makeOwner();

        return new Competition(
            id: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            matchSource: $this->makeMatchSource($owner),
            owner: $owner,
            name: 'Soutěž',
            description: null,
            pin: $pin,
            shareableLinkToken: $token,
            createdAt: $this->now,
        );
    }

    public function testConstructorRecordsCreatedEvent(): void
    {
        $competition = $this->makeCompetition();

        $events = $competition->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CompetitionCreated::class, $events[0]);
        self::assertSame($competition->id, $events[0]->competitionId);
    }

    public function testIsNotDeletedWhenFresh(): void
    {
        $competition = $this->makeCompetition();

        self::assertTrue($competition->isNotDeleted);
        self::assertFalse($competition->isDeleted());
    }

    public function testTipVisibilityDefaultsOnFreshCompetition(): void
    {
        $competition = $this->makeCompetition();

        self::assertFalse($competition->hideOthersTipsBeforeDeadline);
        self::assertNull($competition->tipsDeadline);
    }

    public function testSelectionDefaultsOnFreshCompetition(): void
    {
        $competition = $this->makeCompetition();

        self::assertSame(CompetitionMatchSelectionMode::All, $competition->selectionMode);
        self::assertTrue($competition->includePlayoff);
    }

    public function testConstructorHonorsSelectionAndTipSettings(): void
    {
        $owner = $this->makeOwner();
        $deadline = new \DateTimeImmutable('2025-06-20 10:00:00 UTC');

        $competition = new Competition(
            id: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            matchSource: $this->makeMatchSource($owner),
            owner: $owner,
            name: 'Soutěž',
            description: null,
            pin: null,
            shareableLinkToken: 'token-x',
            createdAt: $this->now,
            selectionMode: CompetitionMatchSelectionMode::Subset,
            includePlayoff: false,
            hideOthersTipsBeforeDeadline: true,
            tipsDeadline: $deadline,
        );

        self::assertSame(CompetitionMatchSelectionMode::Subset, $competition->selectionMode);
        self::assertFalse($competition->includePlayoff);
        self::assertTrue($competition->hideOthersTipsBeforeDeadline);
        self::assertSame($deadline, $competition->tipsDeadline);
    }

    public function testRecordMatchSelectionChangedRecordsEventAndTouchesUpdatedAt(): void
    {
        $competition = $this->makeCompetition();
        $editor = $competition->owner;
        $competition->popEvents();

        $later = new \DateTimeImmutable('2025-06-16 12:00:00 UTC');
        $competition->recordMatchSelectionChanged($editor, $later);

        self::assertSame($later, $competition->updatedAt);

        $events = $competition->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CompetitionMatchSelectionChanged::class, $events[0]);
        self::assertSame($competition->id, $events[0]->competitionId);
        self::assertSame($editor->id, $events[0]->changedByUserId);
    }

    public function testUpdateDetailsRecordsEvent(): void
    {
        $competition = $this->makeCompetition();
        $competition->popEvents();

        $later = new \DateTimeImmutable('2025-06-16 12:00:00 UTC');
        $competition->updateDetails(
            name: 'Nový',
            description: 'Popis',
            hideOthersTipsBeforeDeadline: false,
            tipsDeadline: null,
            now: $later,
        );

        self::assertSame('Nový', $competition->name);
        self::assertSame('Popis', $competition->description);
        self::assertSame($later, $competition->updatedAt);

        $events = $competition->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CompetitionUpdated::class, $events[0]);
    }

    public function testUpdateDetailsAppliesNewTipVisibilityFields(): void
    {
        $competition = $this->makeCompetition();
        $competition->popEvents();

        $deadline = new \DateTimeImmutable('2025-06-19 09:00:00 UTC');
        $competition->updateDetails(
            name: $competition->name,
            description: $competition->description,
            hideOthersTipsBeforeDeadline: true,
            tipsDeadline: $deadline,
            now: $this->now,
        );

        self::assertTrue($competition->hideOthersTipsBeforeDeadline);
        self::assertSame($deadline, $competition->tipsDeadline);
    }

    public function testUpdateDetailsCanClearTipsDeadline(): void
    {
        $competition = $this->makeCompetition();
        $deadline = new \DateTimeImmutable('2025-06-19 09:00:00 UTC');
        $competition->updateDetails(
            name: $competition->name,
            description: $competition->description,
            hideOthersTipsBeforeDeadline: true,
            tipsDeadline: $deadline,
            now: $this->now,
        );

        $competition->updateDetails(
            name: $competition->name,
            description: $competition->description,
            hideOthersTipsBeforeDeadline: false,
            tipsDeadline: null,
            now: $this->now,
        );

        self::assertFalse($competition->hideOthersTipsBeforeDeadline);
        self::assertNull($competition->tipsDeadline);
    }

    public function testSetPinRecordsEvent(): void
    {
        $competition = $this->makeCompetition();
        $competition->popEvents();

        $competition->setPin('12345678', $this->now);

        self::assertSame('12345678', $competition->pin);

        $events = $competition->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CompetitionPinRegenerated::class, $events[0]);
    }

    public function testRevokePinRecordsEventOnlyWhenPresent(): void
    {
        $competition = $this->makeCompetition(pin: '12345678');
        $competition->popEvents();

        $competition->revokePin($this->now);
        self::assertNull($competition->pin);

        $events = $competition->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CompetitionPinRevoked::class, $events[0]);

        // Second revoke is a no-op
        $competition->revokePin($this->now);
        self::assertCount(0, $competition->popEvents());
    }

    public function testSetShareableLinkTokenRecordsEvent(): void
    {
        $competition = $this->makeCompetition();
        $competition->popEvents();

        $competition->setShareableLinkToken('new-token', $this->now);

        self::assertSame('new-token', $competition->shareableLinkToken);

        $events = $competition->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CompetitionShareableLinkRegenerated::class, $events[0]);
    }

    public function testRevokeShareableLinkTokenRecordsEventOnlyWhenPresent(): void
    {
        $competition = $this->makeCompetition(token: 'token-x');
        $competition->popEvents();

        $competition->revokeShareableLinkToken($this->now);
        self::assertNull($competition->shareableLinkToken);

        $events = $competition->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CompetitionShareableLinkRevoked::class, $events[0]);

        $competition->revokeShareableLinkToken($this->now);
        self::assertCount(0, $competition->popEvents());
    }

    public function testSoftDeleteRecordsEvent(): void
    {
        $competition = $this->makeCompetition();
        $competition->popEvents();

        $competition->softDelete($this->now);

        self::assertTrue($competition->isDeleted());
        self::assertFalse($competition->isNotDeleted);

        $events = $competition->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CompetitionDeleted::class, $events[0]);
    }

    public function testSoftDeleteIsIdempotent(): void
    {
        $competition = $this->makeCompetition();
        $firstDelete = new \DateTimeImmutable('2025-06-16 09:00:00 UTC');
        $competition->softDelete($firstDelete);
        $competition->popEvents();

        $competition->softDelete(new \DateTimeImmutable('2025-06-17 09:00:00 UTC'));

        self::assertSame($firstDelete, $competition->deletedAt);
        self::assertCount(0, $competition->popEvents());
    }
}
