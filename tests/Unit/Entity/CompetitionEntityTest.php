<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionSource;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\CompetitionMonetization;
use App\Enum\MatchSourceKind;
use App\Event\CompetitionCreated;
use App\Event\CompetitionDeleted;
use App\Event\CompetitionMatchSelectionChanged;
use App\Event\CompetitionPinRegenerated;
use App\Event\CompetitionPinRevoked;
use App\Event\CompetitionShareableLinkRegenerated;
use App\Event\CompetitionShareableLinkRevoked;
use App\Event\CompetitionTipsLocked;
use App\Event\CompetitionTipsUnlocked;
use App\Event\CompetitionUpdated;
use App\Event\PremiumConfirmed;
use App\Event\PremiumDowngraded;
use App\Exception\CompetitionTipsCannotBeUnlocked;
use App\Exception\CompetitionTipsLockTimeInvalid;
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
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal', 2, 'poločas', 'poločasy'),
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
            headlineSource: $this->makeMatchSource($owner),
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
        self::assertNull($competition->tipsLockedAt);
        self::assertSame(60, $competition->tipChangeOffsetMinutes);
    }

    /**
     * Scope is not a constructor concern any more: a fresh competition has no
     * layers at all, and the first one attached brings the All/playoff defaults.
     */
    public function testAFreshCompetitionHasNoScopeUntilAZdrojIsAttached(): void
    {
        $competition = $this->makeCompetition();

        self::assertSame([], $competition->sources);
        self::assertFalse($competition->isMultiSource);
        // Nothing to have ended, and nothing to manage.
        self::assertFalse($competition->scheduleIsComplete);
        self::assertNull($competition->scopeManageLabel);

        $layer = $this->attachLayer($competition);

        self::assertSame(CompetitionMatchSelectionMode::All, $layer->selectionMode);
        self::assertTrue($layer->includePlayoff);
        self::assertSame([$layer], $competition->sources);
    }

    public function testConstructorHonorsTipSettings(): void
    {
        $owner = $this->makeOwner();

        $competition = new Competition(
            id: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            headlineSource: $this->makeMatchSource($owner),
            owner: $owner,
            name: 'Soutěž',
            description: null,
            pin: null,
            shareableLinkToken: 'token-x',
            createdAt: $this->now,
            hideOthersTipsBeforeDeadline: true,
        );

        self::assertTrue($competition->hideOthersTipsBeforeDeadline);
    }

    /**
     * The headline zdroj follows layer 0. Dropping the first layer must repoint
     * it — otherwise the soutěž keeps advertising, and authorising against, a
     * zdroj it no longer draws from.
     */
    public function testDroppingTheFirstLayerRepointsTheHeadlineZdroj(): void
    {
        $competition = $this->makeCompetition();
        $first = $this->attachLayer($competition);

        $second = new CompetitionSource(
            id: Uuid::v7(),
            competition: $competition,
            matchSource: $this->makeMatchSource($competition->owner),
            addedAt: $this->now,
            position: 1,
        );
        $competition->attachSource($second);

        self::assertSame($first->matchSource, $competition->headlineSource);

        $competition->detachSource($first);

        self::assertSame($second->matchSource, $competition->headlineSource);
        self::assertSame($competition->headlineSource->name, $competition->sourcesLabel);
    }

    /** „Chance Liga a 2 další" — the one home of that copy. */
    public function testSourcesLabelNamesTheHeadlineZdrojAndCountsTheRest(): void
    {
        self::assertSame('Chance Liga', Competition::describeSources('Chance Liga', 1));
        self::assertSame('Chance Liga a 1 další', Competition::describeSources('Chance Liga', 2));
        self::assertSame('Chance Liga a 4 další', Competition::describeSources('Chance Liga', 5));
        self::assertSame('Chance Liga a 5 dalších', Competition::describeSources('Chance Liga', 6));
    }

    private function attachLayer(Competition $competition): CompetitionSource
    {
        $layer = new CompetitionSource(
            id: Uuid::v7(),
            competition: $competition,
            matchSource: $competition->headlineSource,
            addedAt: $this->now,
        );
        $competition->attachSource($layer);

        return $layer;
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
            now: $later,
        );

        self::assertSame('Nový', $competition->name);
        self::assertSame('Popis', $competition->description);
        self::assertSame($later, $competition->updatedAt);

        $events = $competition->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CompetitionUpdated::class, $events[0]);
    }

    public function testUpdateDetailsAppliesTipVisibilityField(): void
    {
        $competition = $this->makeCompetition();
        $competition->popEvents();

        $competition->updateDetails(
            name: $competition->name,
            description: $competition->description,
            hideOthersTipsBeforeDeadline: true,
            now: $this->now,
        );

        self::assertTrue($competition->hideOthersTipsBeforeDeadline);
    }

    public function testLockTipsRecordsEventAndKeepsFirstLockMomentOnRepeat(): void
    {
        $competition = $this->makeCompetition();
        $competition->popEvents();

        $competition->lockTips($this->now);

        self::assertSame($this->now, $competition->tipsLockedAt);
        self::assertSame($this->now, $competition->updatedAt);

        $events = $competition->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CompetitionTipsLocked::class, $events[0]);
        self::assertSame($competition->id, $events[0]->competitionId);

        // Locking again is a no-op: original moment kept, no event.
        $competition->lockTips(new \DateTimeImmutable('2025-06-16 12:00:00 UTC'));
        self::assertSame($this->now, $competition->tipsLockedAt);
        self::assertCount(0, $competition->popEvents());
    }

    public function testScheduleTipsLockStoresFutureMomentWithoutEventAndIsReschedulable(): void
    {
        $competition = $this->makeCompetition();
        $competition->popEvents();

        $firstKickoff = new \DateTimeImmutable('2025-06-20 18:00:00 UTC');
        $scheduled = new \DateTimeImmutable('2025-06-18 09:00:00 UTC');

        $competition->scheduleTipsLock($scheduled, $this->now, $firstKickoff);

        self::assertSame($scheduled, $competition->tipsLockedAt);
        self::assertSame($this->now, $competition->updatedAt);
        // Nothing has locked yet ⇒ no CompetitionTipsLocked event.
        self::assertCount(0, $competition->popEvents());

        // Re-scheduling a pending lock just moves the moment.
        $moved = new \DateTimeImmutable('2025-06-19 20:00:00 UTC');
        $competition->scheduleTipsLock($moved, $this->now, $firstKickoff);
        self::assertSame($moved, $competition->tipsLockedAt);
        self::assertCount(0, $competition->popEvents());
    }

    public function testScheduleTipsLockRejectsMomentInThePast(): void
    {
        $competition = $this->makeCompetition();

        $this->expectException(CompetitionTipsLockTimeInvalid::class);

        $competition->scheduleTipsLock(
            new \DateTimeImmutable('2025-06-15 11:59:00 UTC'),
            $this->now,
            new \DateTimeImmutable('2025-06-20 18:00:00 UTC'),
        );
    }

    public function testScheduleTipsLockRejectsMomentAtOrAfterCompetitionStart(): void
    {
        $competition = $this->makeCompetition();
        $firstKickoff = new \DateTimeImmutable('2025-06-20 18:00:00 UTC');

        $this->expectException(CompetitionTipsLockTimeInvalid::class);

        // At the first kickoff the automatic lock already applies; anything
        // later would push the lock PAST the start and reopen closed tips.
        $competition->scheduleTipsLock($firstKickoff, $this->now, $firstKickoff);
    }

    public function testScheduleTipsLockRejectedOnAlreadyLockedCompetition(): void
    {
        $competition = $this->makeCompetition();
        $competition->lockTips($this->now);
        $competition->popEvents();

        $this->expectException(CompetitionTipsLockTimeInvalid::class);

        $competition->scheduleTipsLock(
            new \DateTimeImmutable('2025-06-18 09:00:00 UTC'),
            $this->now,
            new \DateTimeImmutable('2025-06-20 18:00:00 UTC'),
        );
    }

    public function testLockTipsNowOverridesPendingSchedule(): void
    {
        $competition = $this->makeCompetition();
        $competition->scheduleTipsLock(
            new \DateTimeImmutable('2025-06-18 09:00:00 UTC'),
            $this->now,
            new \DateTimeImmutable('2025-06-20 18:00:00 UTC'),
        );
        $competition->popEvents();

        $competition->lockTips($this->now);

        self::assertSame($this->now, $competition->tipsLockedAt);
        $events = $competition->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CompetitionTipsLocked::class, $events[0]);
    }

    public function testUnlockTipsCancelsPendingSchedule(): void
    {
        $competition = $this->makeCompetition();
        $firstKickoff = new \DateTimeImmutable('2025-06-20 18:00:00 UTC');
        $competition->scheduleTipsLock(new \DateTimeImmutable('2025-06-18 09:00:00 UTC'), $this->now, $firstKickoff);
        $competition->popEvents();

        $competition->unlockTips($this->now, $firstKickoff);

        self::assertNull($competition->tipsLockedAt);
        self::assertCount(1, $competition->popEvents());
    }

    public function testUnlockTipsAllowedBeforeFirstKickoff(): void
    {
        $competition = $this->makeCompetition();
        $competition->lockTips($this->now);
        $competition->popEvents();

        $competition->unlockTips($this->now, new \DateTimeImmutable('2025-06-20 18:00:00 UTC'));

        self::assertNull($competition->tipsLockedAt);

        $events = $competition->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CompetitionTipsUnlocked::class, $events[0]);
    }

    public function testUnlockTipsAllowedWhenCompetitionHasNoMatches(): void
    {
        $competition = $this->makeCompetition();
        $competition->lockTips($this->now);
        $competition->popEvents();

        $competition->unlockTips($this->now, null);

        self::assertNull($competition->tipsLockedAt);
        self::assertCount(1, $competition->popEvents());
    }

    public function testUnlockTipsRejectedAfterFirstKickoff(): void
    {
        $competition = $this->makeCompetition();
        $competition->lockTips($this->now);
        $competition->popEvents();

        $this->expectException(CompetitionTipsCannotBeUnlocked::class);

        // First kickoff was an hour ago — the competition genuinely started.
        $competition->unlockTips($this->now, new \DateTimeImmutable('2025-06-15 11:00:00 UTC'));
    }

    public function testUnlockTipsOnUnlockedCompetitionIsNoOp(): void
    {
        $competition = $this->makeCompetition();
        $competition->popEvents();

        // Even a passed first kickoff must not throw — there is nothing to undo.
        $competition->unlockTips($this->now, new \DateTimeImmutable('2025-06-15 11:00:00 UTC'));

        self::assertNull($competition->tipsLockedAt);
        self::assertCount(0, $competition->popEvents());
    }

    public function testPinTipsLockMomentSetsMomentWithoutEventAndIsIdempotent(): void
    {
        $competition = $this->makeCompetition();
        $competition->popEvents();

        $pinned = new \DateTimeImmutable('2025-06-10 18:00:00 UTC');
        $competition->pinTipsLockMoment($pinned, $this->now);

        self::assertEquals($pinned, $competition->tipsLockedAt);
        // Correctness pin, not a user action ⇒ records NO domain event.
        self::assertCount(0, $competition->popEvents());

        // Idempotent: a later pin (or manual lock) never overwrites the moment.
        $competition->pinTipsLockMoment(new \DateTimeImmutable('2025-06-12 09:00:00 UTC'), $this->now);
        self::assertEquals($pinned, $competition->tipsLockedAt);
    }

    public function testChangeTipChangeOffsetValidatesAndApplies(): void
    {
        $competition = $this->makeCompetition();
        $competition->popEvents();

        $competition->changeTipChangeOffset(120, $this->now);
        self::assertSame(120, $competition->tipChangeOffsetMinutes);

        $this->expectException(\InvalidArgumentException::class);
        $competition->changeTipChangeOffset(-1, $this->now);
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

    public function testFreshCompetitionMonetizationDefaultsToNone(): void
    {
        $competition = $this->makeCompetition();

        self::assertSame(CompetitionMonetization::None, $competition->monetization);
        self::assertNull($competition->premiumReconciledAt);
        self::assertFalse($competition->premiumShowDistribution);
        self::assertFalse($competition->premiumShowOthersTips);
        self::assertFalse($competition->premiumAllowTipChanges);
    }

    public function testEnablePremiumSetsPremiumAndResetsReconciliation(): void
    {
        $competition = $this->makeCompetition();
        $competition->popEvents();

        // A prior reconciliation stamp must be cleared so the competition is
        // reconciled again at its next start.
        $competition->markPremiumReconciled($this->now);
        $competition->popEvents();
        self::assertNotNull($competition->premiumReconciledAt);

        $later = $this->now->modify('+1 hour');
        $competition->enablePremium($later);

        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);
        self::assertNull($competition->premiumReconciledAt);
        self::assertSame($later, $competition->updatedAt);
        // enablePremium is a state flip, not a domain fact — no event.
        self::assertCount(0, $competition->popEvents());
    }

    public function testSwitchToBoostsLeavesReconciliationStampUntouched(): void
    {
        $competition = $this->makeCompetition();
        $competition->enablePremium($this->now);
        $competition->markPremiumReconciled($this->now);
        $competition->popEvents();
        $stamp = $competition->premiumReconciledAt;
        self::assertNotNull($stamp);

        $competition->switchToBoosts($this->now->modify('+1 day'));

        self::assertSame(CompetitionMonetization::Boosts, $competition->monetization);
        self::assertSame($stamp, $competition->premiumReconciledAt);
        self::assertCount(0, $competition->popEvents());
    }

    public function testMarkPremiumReconciledStampsAndRecordsConfirmed(): void
    {
        $competition = $this->makeCompetition();
        $competition->enablePremium($this->now);
        $competition->popEvents();

        $competition->markPremiumReconciled($this->now);

        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);
        self::assertSame($this->now, $competition->premiumReconciledAt);

        $events = $competition->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PremiumConfirmed::class, $events[0]);
        self::assertSame($competition->id, $events[0]->competitionId);
        self::assertSame($competition->owner->id, $events[0]->ownerId);
    }

    public function testMarkPremiumReconciledIsIdempotent(): void
    {
        $competition = $this->makeCompetition();
        $competition->markPremiumReconciled($this->now);
        $competition->popEvents();

        $competition->markPremiumReconciled($this->now->modify('+1 day'));

        self::assertSame($this->now, $competition->premiumReconciledAt);
        self::assertCount(0, $competition->popEvents());
    }

    public function testDowngradeToBoostsSwitchesStampsAndRecordsDowngraded(): void
    {
        $competition = $this->makeCompetition();
        $competition->enablePremium($this->now);
        $competition->popEvents();

        $competition->downgradeToBoosts($this->now);

        self::assertSame(CompetitionMonetization::Boosts, $competition->monetization);
        self::assertSame($this->now, $competition->premiumReconciledAt);

        $events = $competition->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PremiumDowngraded::class, $events[0]);
        self::assertSame($competition->id, $events[0]->competitionId);
        self::assertSame($competition->owner->id, $events[0]->ownerId);
    }

    public function testDowngradeToBoostsIsIdempotent(): void
    {
        $competition = $this->makeCompetition();
        $competition->markPremiumReconciled($this->now);
        $competition->popEvents();

        // Already reconciled ⇒ a downgrade attempt must not re-fire or re-flip.
        $competition->downgradeToBoosts($this->now->modify('+1 day'));

        self::assertSame(CompetitionMonetization::None, $competition->monetization);
        self::assertSame($this->now, $competition->premiumReconciledAt);
        self::assertCount(0, $competition->popEvents());
    }

    public function testSetPremiumFeaturesUpdatesTogglesAndOffset(): void
    {
        $competition = $this->makeCompetition();
        $competition->popEvents();

        $later = $this->now->modify('+2 hours');
        $competition->setPremiumFeatures(
            showDistribution: true,
            showOthersTips: true,
            allowTipChanges: true,
            tipChangeOffsetMinutes: 90,
            now: $later,
        );

        self::assertTrue($competition->premiumShowDistribution);
        self::assertTrue($competition->premiumShowOthersTips);
        self::assertTrue($competition->premiumAllowTipChanges);
        self::assertSame(90, $competition->tipChangeOffsetMinutes);
        self::assertSame($later, $competition->updatedAt);
        // Tuning knobs — no domain event.
        self::assertCount(0, $competition->popEvents());
    }

    public function testSetPremiumFeaturesRejectsNegativeOffset(): void
    {
        $competition = $this->makeCompetition();

        $this->expectException(\InvalidArgumentException::class);

        $competition->setPremiumFeatures(
            showDistribution: false,
            showOthersTips: false,
            allowTipChanges: false,
            tipChangeOffsetMinutes: -1,
            now: $this->now,
        );
    }
}
