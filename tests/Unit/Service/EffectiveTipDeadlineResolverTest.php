<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionMatchSelection;
use App\Entity\CompetitionMatchSetting;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\MatchSourceKind;
use App\Repository\CompetitionMatchSelectionRepository;
use App\Repository\CompetitionMatchSettingRepository;
use App\Service\Competition\CompetitionEntitlements;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Unit matrix for the tip-locking contract (S07). All times UTC unless noted;
 * Europe/Prague is UTC+2 in June (CEST) — day-boundary cases test exactly that.
 *
 * Fixed "now" is 2025-06-15 12:00 UTC (matches MockClock), but the resolver
 * itself is clock-free: it returns deadlines, callers compare with now.
 */
final class EffectiveTipDeadlineResolverTest extends TestCase
{
    private \DateTimeImmutable $now;

    /** Matches the provider stub serves per competition UUID, kickoff-ordered. */
    /** @var array<string, list<SportMatch>> */
    private array $providedMatches = [];

    /** @var array<string, CompetitionMatchSetting> "competitionId|matchId" → override */
    private array $overrides = [];

    /** @var array<string, list<CompetitionMatchSelection>> competition UUID → selections */
    private array $selections = [];

    private bool $canChangeTips = false;

    private int $matchSequence = 0;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $this->providedMatches = [];
        $this->overrides = [];
        $this->selections = [];
        $this->canChangeTips = false;
        $this->matchSequence = 0;
    }

    // ── Branch 4: default — lock at competition start ────────────────────────

    public function testBeforeFirstKickoffEveryMatchLocksAtFirstKickoff(): void
    {
        // Competition not started: first kickoff 2025-06-20 18:00 (future).
        $competition = $this->makeCompetition();
        $first = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $later = $this->makeMatch($competition, kickoff: '2025-06-25 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$first, $later]);

        $resolver = $this->resolver();

        // The day-1 match locks at its own kickoff (= first kickoff)…
        self::assertEquals(new \DateTimeImmutable('2025-06-20 18:00'), $resolver->deadlineFor($competition, $first));
        // …and every later match locks at the competition start too.
        self::assertEquals(new \DateTimeImmutable('2025-06-20 18:00'), $resolver->deadlineFor($competition, $later));

        // Pre-lock ⇒ open now; post-first-kickoff ⇒ locked.
        self::assertFalse($resolver->isLocked($competition, $later, null, $this->now));
        self::assertTrue($resolver->isLocked($competition, $later, null, new \DateTimeImmutable('2025-06-20 18:00:01')));
    }

    public function testAfterFirstKickoffAllNonLateMatchesAreLocked(): void
    {
        // First kickoff 2025-06-10 (past) — competition started, tips locked.
        $competition = $this->makeCompetition();
        $past = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $future = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$past, $future]);

        $resolver = $this->resolver();

        self::assertEquals(new \DateTimeImmutable('2025-06-10 18:00'), $resolver->deadlineFor($competition, $future));
        self::assertTrue($resolver->isLocked($competition, $future, null, $this->now));
    }

    public function testCompetitionWithoutMatchesFallsBackToKickoff(): void
    {
        // Defensive edge: no included matches ⇒ no lock moment ⇒ own kickoff.
        $competition = $this->makeCompetition();
        $foreign = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, []);

        self::assertEquals(
            new \DateTimeImmutable('2025-06-20 18:00'),
            $this->resolver()->deadlineFor($competition, $foreign),
        );
    }

    // ── Manual lock / unlock ─────────────────────────────────────────────────

    public function testManualLockMovesTheDeadlineToTheLockMoment(): void
    {
        $competition = $this->makeCompetition();
        $match = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$match]);

        $lockedAt = new \DateTimeImmutable('2025-06-14 09:00:00 UTC');
        $competition->lockTips($lockedAt);

        $resolver = $this->resolver();

        self::assertEquals($lockedAt, $resolver->deadlineFor($competition, $match));
        // Locked at that very moment — now (June 15) is past it.
        self::assertTrue($resolver->isLocked($competition, $match, null, $this->now));
        self::assertFalse($resolver->isLocked($competition, $match, null, new \DateTimeImmutable('2025-06-14 08:59:59')));
    }

    public function testUnlockRestoresTheFirstKickoffDefault(): void
    {
        $competition = $this->makeCompetition();
        $match = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$match]);

        $competition->lockTips(new \DateTimeImmutable('2025-06-14 09:00:00 UTC'));
        // Unlock is allowed: first kickoff (June 20) is still ahead.
        $competition->unlockTips($this->now, $match->kickoffAt);

        $resolver = $this->resolver();

        self::assertEquals(new \DateTimeImmutable('2025-06-20 18:00'), $resolver->deadlineFor($competition, $match));
        self::assertFalse($resolver->isLocked($competition, $match, null, $this->now));
    }

    public function testMatchCreatedAfterManualLockGetsItsOwnKickoff(): void
    {
        $competition = $this->makeCompetition();
        $old = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $competition->lockTips(new \DateTimeImmutable('2025-06-14 09:00:00 UTC'));
        // Created after the manual lock ⇒ late-added ⇒ own kickoff.
        $late = $this->makeMatch($competition, kickoff: '2025-06-25 18:00', createdAt: '2025-06-15 11:00');
        $this->provideMatches($competition, [$old, $late]);

        $resolver = $this->resolver();

        self::assertEquals(new \DateTimeImmutable('2025-06-14 09:00'), $resolver->deadlineFor($competition, $old));
        self::assertEquals(new \DateTimeImmutable('2025-06-25 18:00'), $resolver->deadlineFor($competition, $late));
    }

    // ── Competition created after the source already started ─────────────────

    public function testAllModeCompetitionCreatedAfterSourceFirstKickoffKeepsPreExistingMatchesTippable(): void
    {
        // Competition created June 15 — AFTER the source's first match already
        // kicked off (June 10). Every pre-existing match "entered" at competition
        // creation, so none is treated as pre-lock: each is late-added and stays
        // tippable until its OWN kickoff (deadline = kickoff), not the June-10
        // lock moment. Would wrongly lock the June-20 match before this fix.
        $competition = $this->makeCompetition(createdAt: '2025-06-15 12:00:00 UTC');
        $already = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $future = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$already, $future]);

        $resolver = $this->resolver();

        self::assertEquals(new \DateTimeImmutable('2025-06-10 18:00'), $resolver->deadlineFor($competition, $already));
        self::assertEquals(new \DateTimeImmutable('2025-06-20 18:00'), $resolver->deadlineFor($competition, $future));
        self::assertFalse($resolver->isLocked($competition, $future, null, $this->now));
    }

    public function testSubsetTwinOfCompetitionCreatedAfterSourceFirstKickoffProvesParity(): void
    {
        // Parity: the same scenario in Subset mode already worked (initial
        // selections get addedAt = competition creation, June 15 > June 10 lock).
        // Both modes must agree — All now composes max(match.createdAt, C.createdAt).
        $competition = $this->makeCompetition(CompetitionMatchSelectionMode::Subset, '2025-06-15 12:00:00 UTC');
        $already = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $future = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$already, $future]);
        $this->select($competition, $already, addedAt: '2025-06-15 12:00');
        $this->select($competition, $future, addedAt: '2025-06-15 12:00');

        $resolver = $this->resolver();

        self::assertEquals(new \DateTimeImmutable('2025-06-10 18:00'), $resolver->deadlineFor($competition, $already));
        self::assertEquals(new \DateTimeImmutable('2025-06-20 18:00'), $resolver->deadlineFor($competition, $future));
        self::assertFalse($resolver->isLocked($competition, $future, null, $this->now));
    }

    // ── Pin the lock moment when its defining match leaves (postpone/delete) ──

    public function testPinsLockMomentWhenPostponedOpenerWasLockDefiningAndReached(): void
    {
        // Opener kicked off June 10 (past, lock reached) and is postponed to
        // June 28: the post-change list has the sibling (June 20) first, then the
        // moved opener. Naive recompute would jump the lock to June 20 and reopen
        // the sibling ⇒ the reached June-10 moment must be pinned.
        $competition = $this->makeCompetition();
        $sibling = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-05-01 10:00');
        $movedOpener = $this->makeMatch($competition, kickoff: '2025-06-28 18:00', createdAt: '2025-05-01 10:00');
        $this->provideMatches($competition, [$sibling, $movedOpener]);

        $pinAt = $this->resolver()->lockMomentToPinAfterDefiningMatchLeft(
            $competition,
            $movedOpener,
            new \DateTimeImmutable('2025-06-10 18:00'),
            $this->now,
        );

        self::assertEquals(new \DateTimeImmutable('2025-06-10 18:00'), $pinAt);
    }

    public function testPinsLockMomentWhenLockDefiningOpenerDeletedAfterLock(): void
    {
        // Soft-delete drops the opener from the included list — same pin.
        $competition = $this->makeCompetition();
        $sibling = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-05-01 10:00');
        $deletedOpener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-05-01 10:00');
        $this->provideMatches($competition, [$sibling]); // opener excluded post-delete

        $pinAt = $this->resolver()->lockMomentToPinAfterDefiningMatchLeft(
            $competition,
            $deletedOpener,
            $deletedOpener->kickoffAt,
            $this->now,
        );

        self::assertEquals(new \DateTimeImmutable('2025-06-10 18:00'), $pinAt);
    }

    public function testDoesNotPinWhenLockMomentNotYetReached(): void
    {
        // Opener kickoff June 18 is still in the future (now June 15): the
        // competition has not started, postponing it may legitimately reopen.
        $competition = $this->makeCompetition();
        $sibling = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-05-01 10:00');
        $movedOpener = $this->makeMatch($competition, kickoff: '2025-06-28 18:00', createdAt: '2025-05-01 10:00');
        $this->provideMatches($competition, [$sibling, $movedOpener]);

        self::assertNull($this->resolver()->lockMomentToPinAfterDefiningMatchLeft(
            $competition,
            $movedOpener,
            new \DateTimeImmutable('2025-06-18 18:00'),
            $this->now,
        ));
    }

    public function testDoesNotPinWhenLeavingMatchWasNotLockDefining(): void
    {
        // The opener (June 10) stays and still defines the lock; a LATER match
        // (June 20) is postponed after it played — nothing to pin.
        $competition = $this->makeCompetition();
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-05-01 10:00');
        $moved = $this->makeMatch($competition, kickoff: '2025-06-28 18:00', createdAt: '2025-05-01 10:00');
        $this->provideMatches($competition, [$opener, $moved]);

        self::assertNull($this->resolver()->lockMomentToPinAfterDefiningMatchLeft(
            $competition,
            $moved,
            new \DateTimeImmutable('2025-06-20 18:00'),
            new \DateTimeImmutable('2025-06-25 12:00:00 UTC'),
        ));
    }

    public function testDoesNotPinWhenCompetitionAlreadyLocked(): void
    {
        $competition = $this->makeCompetition();
        $sibling = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-05-01 10:00');
        $movedOpener = $this->makeMatch($competition, kickoff: '2025-06-28 18:00', createdAt: '2025-05-01 10:00');
        $this->provideMatches($competition, [$sibling, $movedOpener]);
        $competition->lockTips(new \DateTimeImmutable('2025-06-05 09:00:00 UTC'));

        self::assertNull($this->resolver()->lockMomentToPinAfterDefiningMatchLeft(
            $competition,
            $movedOpener,
            new \DateTimeImmutable('2025-06-10 18:00'),
            $this->now,
        ));
    }

    // ── Branch 3: late-added matches ─────────────────────────────────────────

    public function testLateAddedMatchInAllModeLocksAtOwnKickoff(): void
    {
        // Competition started June 10; playoff match created June 15 ⇒ late-added.
        $competition = $this->makeCompetition();
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $playoff = $this->makeMatch($competition, kickoff: '2025-06-22 18:00', createdAt: '2025-06-15 09:00');
        $this->provideMatches($competition, [$opener, $playoff]);

        $resolver = $this->resolver();

        self::assertEquals(new \DateTimeImmutable('2025-06-22 18:00'), $resolver->deadlineFor($competition, $playoff));
        self::assertFalse($resolver->isLocked($competition, $playoff, null, $this->now));
    }

    public function testLateAddedSelectionInSubsetModeLocksAtOwnKickoff(): void
    {
        $competition = $this->makeCompetition(CompetitionMatchSelectionMode::Subset);
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        // The MATCH itself is old (createdAt before lock) — only its selection
        // row was added late. Subset mode keys off the selection's addedAt.
        $lateSelected = $this->makeMatch($competition, kickoff: '2025-06-22 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$opener, $lateSelected]);
        $this->select($competition, $opener, addedAt: '2025-06-01 10:00');
        $this->select($competition, $lateSelected, addedAt: '2025-06-15 09:00');

        $resolver = $this->resolver();

        self::assertEquals(new \DateTimeImmutable('2025-06-10 18:00'), $resolver->deadlineFor($competition, $opener));
        self::assertEquals(new \DateTimeImmutable('2025-06-22 18:00'), $resolver->deadlineFor($competition, $lateSelected));
    }

    public function testSubsetSelectionAddedBeforeLockFollowsTheLockMoment(): void
    {
        $competition = $this->makeCompetition(CompetitionMatchSelectionMode::Subset);
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $regular = $this->makeMatch($competition, kickoff: '2025-06-22 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$opener, $regular]);
        $this->select($competition, $opener, addedAt: '2025-06-01 10:00');
        $this->select($competition, $regular, addedAt: '2025-06-05 10:00');

        self::assertEquals(
            new \DateTimeImmutable('2025-06-10 18:00'),
            $this->resolver()->deadlineFor($competition, $regular),
        );
    }

    // ── Branch 2: manager per-match override ─────────────────────────────────

    public function testOverrideBeatsTheCompetitionDefault(): void
    {
        // Competition started June 10 (locked), but the manager granted this
        // match an explicit later deadline — the override wins.
        $competition = $this->makeCompetition();
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $match = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$opener, $match]);
        $this->override($competition, $match, deadline: '2025-06-20 17:30');

        $resolver = $this->resolver();

        self::assertEquals(new \DateTimeImmutable('2025-06-20 17:30'), $resolver->deadlineFor($competition, $match));
        self::assertFalse($resolver->isLocked($competition, $match, null, $this->now));
    }

    public function testOverrideAlsoBeatsTheLateAddedOwnKickoffRule(): void
    {
        // Primary override use case: cap a late playoff match EARLIER than its kickoff.
        $competition = $this->makeCompetition();
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $playoff = $this->makeMatch($competition, kickoff: '2025-06-22 18:00', createdAt: '2025-06-15 09:00');
        $this->provideMatches($competition, [$opener, $playoff]);
        $this->override($competition, $playoff, deadline: '2025-06-22 12:00');

        self::assertEquals(
            new \DateTimeImmutable('2025-06-22 12:00'),
            $this->resolver()->deadlineFor($competition, $playoff),
        );
    }

    public function testOverrideIsCappedAtKickoffWhenKickoffMovedEarlier(): void
    {
        // Override was ≤ kickoff at write time; the match then moved EARLIER
        // (postponement/reschedule) — the "never later than kickoff" cap tracks
        // the NEW kickoff at read time.
        $competition = $this->makeCompetition();
        $match = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$match]);
        $this->override($competition, $match, deadline: '2025-06-20 17:30');

        $match->postponeTo(new \DateTimeImmutable('2025-06-20 12:00'), $this->now);

        self::assertEquals(
            new \DateTimeImmutable('2025-06-20 12:00'),
            $this->resolver()->deadlineFor($competition, $match),
        );
    }

    // ── Postponement: does NOT reopen tipping ────────────────────────────────

    public function testPostponedMatchMovedAfterLockStaysLocked(): void
    {
        // Non-late-added match of a started competition: kickoff moves to next
        // week, but the deadline stays the (long past) lock moment — a
        // postponement never reopens tipping.
        $competition = $this->makeCompetition();
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $moved = $this->makeMatch($competition, kickoff: '2025-06-16 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$opener, $moved]);

        $moved->postponeTo(new \DateTimeImmutable('2025-06-28 18:00'), $this->now);
        $moved->reschedule(new \DateTimeImmutable('2025-06-28 18:00'), $this->now);

        $resolver = $this->resolver();

        self::assertEquals(new \DateTimeImmutable('2025-06-10 18:00'), $resolver->deadlineFor($competition, $moved));
        self::assertTrue($resolver->isLocked($competition, $moved, null, $this->now));
    }

    public function testLateAddedPostponedMatchFollowsItsNewKickoff(): void
    {
        // Late-added matches tip until their own kickoff — after a postponement
        // that is the NEW kickoff (while postponed, isOpenForGuesses gates).
        $competition = $this->makeCompetition();
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $late = $this->makeMatch($competition, kickoff: '2025-06-22 18:00', createdAt: '2025-06-15 09:00');
        $this->provideMatches($competition, [$opener, $late]);

        $late->postponeTo(new \DateTimeImmutable('2025-06-29 18:00'), $this->now);

        $resolver = $this->resolver();

        self::assertEquals(new \DateTimeImmutable('2025-06-29 18:00'), $resolver->deadlineFor($competition, $late));
        // Postponed state ⇒ locked despite the future deadline…
        self::assertTrue($resolver->isLocked($competition, $late, null, $this->now));

        // …and open again once rescheduled.
        $late->reschedule(new \DateTimeImmutable('2025-06-29 18:00'), $this->now);
        self::assertFalse($resolver->isLocked($competition, $late, null, $this->now));
    }

    // ── Entitlement branch („Měnit tip") ─────────────────────────────────────

    public function testEntitledUserMayChangeUntilOffsetBeforeDaysFirstMatch(): void
    {
        // Started competition (locked since June 10). Two matches share the
        // Prague day June 20: 14:00 and 18:00 UTC. Entitled deadline for BOTH
        // is day-first (14:00) − 60 min = 13:00 UTC.
        $competition = $this->makeCompetition();
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $dayFirst = $this->makeMatch($competition, kickoff: '2025-06-20 14:00', createdAt: '2025-06-01 10:00');
        $daySecond = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$opener, $dayFirst, $daySecond]);
        $this->canChangeTips = true;

        $resolver = $this->resolver();
        $user = $this->makeUser();

        self::assertEquals(new \DateTimeImmutable('2025-06-20 13:00'), $resolver->deadlineFor($competition, $daySecond, $user));
        // The day's FIRST match: entitlement gives 13:00, but the base rule
        // (lock ≤ kickoff... here lock is past) is 06-10; max() keeps 13:00.
        self::assertEquals(new \DateTimeImmutable('2025-06-20 13:00'), $resolver->deadlineFor($competition, $dayFirst, $user));

        // Without a user (or without the entitlement) the lock moment holds.
        self::assertEquals(new \DateTimeImmutable('2025-06-10 18:00'), $resolver->deadlineFor($competition, $daySecond));
    }

    public function testEntitlementNeverShortensTheDefaultWindow(): void
    {
        // Day-1 of a NOT yet started competition: base deadline = first kickoff
        // (June 20 18:00). The entitlement alone would give 17:00 — a boost must
        // never shorten the window, so max() keeps 18:00.
        $competition = $this->makeCompetition();
        $first = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$first]);
        $this->canChangeTips = true;

        self::assertEquals(
            new \DateTimeImmutable('2025-06-20 18:00'),
            $this->resolver()->deadlineFor($competition, $first, $this->makeUser()),
        );
    }

    public function testEntitledDeadlineUsesEachMatchsOwnPragueDay(): void
    {
        // Matches on DIFFERENT Prague days each follow their own day's first match.
        $competition = $this->makeCompetition();
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $day20 = $this->makeMatch($competition, kickoff: '2025-06-20 14:00', createdAt: '2025-06-01 10:00');
        $day21 = $this->makeMatch($competition, kickoff: '2025-06-21 16:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$opener, $day20, $day21]);
        $this->canChangeTips = true;

        $resolver = $this->resolver();
        $user = $this->makeUser();

        self::assertEquals(new \DateTimeImmutable('2025-06-20 13:00'), $resolver->deadlineFor($competition, $day20, $user));
        self::assertEquals(new \DateTimeImmutable('2025-06-21 15:00'), $resolver->deadlineFor($competition, $day21, $user));
    }

    public function testPragueDayBoundaryGroupsLateUtcEveningWithNextDay(): void
    {
        // 2025-06-20 23:30 UTC = 2025-06-21 01:30 Europe/Prague (CEST, UTC+2):
        // the match belongs to the PRAGUE day June 21, whose first match is the
        // 21st 10:00 UTC — NOT to the June 20 games.
        $competition = $this->makeCompetition();
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $day20 = $this->makeMatch($competition, kickoff: '2025-06-20 14:00', createdAt: '2025-06-01 10:00');
        $lateEvening = $this->makeMatch($competition, kickoff: '2025-06-20 23:30', createdAt: '2025-06-01 10:00');
        $day21 = $this->makeMatch($competition, kickoff: '2025-06-21 10:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$opener, $day20, $lateEvening, $day21]);
        $this->canChangeTips = true;

        $resolver = $this->resolver();
        $user = $this->makeUser();

        // Prague-day June 21 starts with the 23:30 UTC match itself (01:30 local).
        self::assertEquals(new \DateTimeImmutable('2025-06-20 22:30'), $resolver->deadlineFor($competition, $lateEvening, $user));
        self::assertEquals(new \DateTimeImmutable('2025-06-20 22:30'), $resolver->deadlineFor($competition, $day21, $user));
        self::assertEquals(new \DateTimeImmutable('2025-06-20 13:00'), $resolver->deadlineFor($competition, $day20, $user));
    }

    public function testEntitledOffsetIsManagerConfigurable(): void
    {
        $competition = $this->makeCompetition();
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $match = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$opener, $match]);
        $this->canChangeTips = true;

        $competition->changeTipChangeOffset(120, $this->now);

        self::assertEquals(
            new \DateTimeImmutable('2025-06-20 16:00'),
            $this->resolver()->deadlineFor($competition, $match, $this->makeUser()),
        );
    }

    public function testEntitledDeadlineNeverExceedsTheMatchKickoff(): void
    {
        // Offset 0 ⇒ entitled deadline = day-first kickoff itself; for the
        // day's first match that equals its kickoff — the cap holds exactly.
        $competition = $this->makeCompetition();
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $match = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$opener, $match]);
        $this->canChangeTips = true;

        $competition->changeTipChangeOffset(0, $this->now);

        $deadline = $this->resolver()->deadlineFor($competition, $match, $this->makeUser());

        self::assertEquals(new \DateTimeImmutable('2025-06-20 18:00'), $deadline);
        self::assertLessThanOrEqual($match->kickoffAt, $deadline);
    }

    public function testEntitledDayFirstConsidersOnlyIncludedMatchesInSubsetMode(): void
    {
        // The SOURCE has a 09:00 UTC match that day, but the competition did
        // not select it — day-first must be the first INCLUDED match (12:00).
        $competition = $this->makeCompetition(CompetitionMatchSelectionMode::Subset);
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $selectedNoon = $this->makeMatch($competition, kickoff: '2025-06-20 12:00', createdAt: '2025-06-01 10:00');
        $selectedAfternoon = $this->makeMatch($competition, kickoff: '2025-06-20 15:00', createdAt: '2025-06-01 10:00');
        // The 09:00 source match exists but is NOT in the provided (included) list.
        $this->makeMatch($competition, kickoff: '2025-06-20 09:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$opener, $selectedNoon, $selectedAfternoon]);
        $this->select($competition, $opener, addedAt: '2025-06-01 10:00');
        $this->select($competition, $selectedNoon, addedAt: '2025-06-01 10:00');
        $this->select($competition, $selectedAfternoon, addedAt: '2025-06-01 10:00');
        $this->canChangeTips = true;

        self::assertEquals(
            new \DateTimeImmutable('2025-06-20 11:00'),
            $this->resolver()->deadlineFor($competition, $selectedAfternoon, $this->makeUser()),
        );
    }

    public function testEntitlementExtendsPastAManagerOverrideWhenLater(): void
    {
        // Manager override caps a late playoff match at 12:00; the „Měnit tip"
        // boost promises changes until day-first − offset (17:00). The boost's
        // promise wins where later — entitlements only ever EXTEND.
        $competition = $this->makeCompetition();
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $playoff = $this->makeMatch($competition, kickoff: '2025-06-22 18:00', createdAt: '2025-06-15 09:00');
        $this->provideMatches($competition, [$opener, $playoff]);
        $this->override($competition, $playoff, deadline: '2025-06-22 12:00');
        $this->canChangeTips = true;

        $resolver = $this->resolver();

        self::assertEquals(new \DateTimeImmutable('2025-06-22 17:00'), $resolver->deadlineFor($competition, $playoff, $this->makeUser()));
        // Non-entitled users keep the override.
        self::assertEquals(new \DateTimeImmutable('2025-06-22 12:00'), $resolver->deadlineFor($competition, $playoff));
    }

    // ── Batch + locking gate ─────────────────────────────────────────────────

    public function testDeadlinesForMatchesTheSingleResolutionForEveryMatch(): void
    {
        $competition = $this->makeCompetition();
        $opener = $this->makeMatch($competition, kickoff: '2025-06-10 18:00', createdAt: '2025-06-01 10:00');
        $regular = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $late = $this->makeMatch($competition, kickoff: '2025-06-22 18:00', createdAt: '2025-06-15 09:00');
        $overridden = $this->makeMatch($competition, kickoff: '2025-06-23 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$opener, $regular, $late, $overridden]);
        $this->override($competition, $overridden, deadline: '2025-06-23 12:00');

        $resolver = $this->resolver();
        $batch = $resolver->deadlinesFor($competition, [$opener, $regular, $late, $overridden]);

        foreach ([$opener, $regular, $late, $overridden] as $match) {
            self::assertEquals(
                $resolver->deadlineFor($competition, $match),
                $batch[$match->id->toRfc4122()],
            );
        }

        self::assertSame([], $resolver->deadlinesFor($competition, []));
    }

    public function testIsLockedRespectsMatchStateRegardlessOfDeadline(): void
    {
        $competition = $this->makeCompetition();
        $match = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$match]);

        $resolver = $this->resolver();
        self::assertFalse($resolver->isLocked($competition, $match, null, $this->now));

        $match->cancel($this->now);
        self::assertTrue($resolver->isLocked($competition, $match, null, $this->now));
    }

    public function testLockMomentAndFirstKickoffHelpers(): void
    {
        $competition = $this->makeCompetition();
        $first = $this->makeMatch($competition, kickoff: '2025-06-20 18:00', createdAt: '2025-06-01 10:00');
        $later = $this->makeMatch($competition, kickoff: '2025-06-25 18:00', createdAt: '2025-06-01 10:00');
        $this->provideMatches($competition, [$first, $later]);

        $resolver = $this->resolver();

        self::assertEquals($first->kickoffAt, $resolver->firstKickoffFor($competition));
        self::assertEquals($first->kickoffAt, $resolver->lockMomentFor($competition));

        $lockedAt = new \DateTimeImmutable('2025-06-14 09:00:00 UTC');
        $competition->lockTips($lockedAt);

        self::assertEquals($lockedAt, $resolver->lockMomentFor($competition));
        // First kickoff is unaffected by a manual lock.
        self::assertEquals($first->kickoffAt, $resolver->firstKickoffFor($competition));
    }

    // ── Fixture builders / stubs ─────────────────────────────────────────────

    private function resolver(): EffectiveTipDeadlineResolver
    {
        $provider = $this->createStub(CompetitionMatchProvider::class);
        $provider->method('matchesFor')->willReturnCallback(
            fn (Competition $competition): array => $this->providedMatches[$competition->id->toRfc4122()] ?? [],
        );
        // Every match built here lives on its competition's own source and is not
        // playoff-excluded ⇒ it belongs. The pin path uses this membership test.
        $provider->method('includesIgnoringDeletion')->willReturn(true);

        $overrideRepo = $this->createStub(CompetitionMatchSettingRepository::class);
        $overrideRepo->method('findByCompetitionAndMatch')->willReturnCallback(
            fn (Uuid $competitionId, Uuid $matchId): ?CompetitionMatchSetting => $this->overrides[$competitionId->toRfc4122().'|'.$matchId->toRfc4122()] ?? null,
        );
        $overrideRepo->method('findByCompetitionAndMatches')->willReturnCallback(
            function (Uuid $competitionId, array $matchIds): array {
                $result = [];

                foreach ($matchIds as $matchId) {
                    $override = $this->overrides[$competitionId->toRfc4122().'|'.$matchId->toRfc4122()] ?? null;

                    if (null !== $override) {
                        $result[$matchId->toRfc4122()] = $override;
                    }
                }

                return $result;
            },
        );

        $selectionRepo = $this->createStub(CompetitionMatchSelectionRepository::class);
        $selectionRepo->method('listByCompetition')->willReturnCallback(
            fn (Uuid $competitionId): array => $this->selections[$competitionId->toRfc4122()] ?? [],
        );

        $entitlements = new class ($this) extends CompetitionEntitlements {
            public function __construct(private readonly EffectiveTipDeadlineResolverTest $test)
            {
            }

            public function canChangeTips(Competition $competition, User $user): bool
            {
                return $this->test->grantsTipChanges();
            }
        };

        return new EffectiveTipDeadlineResolver($provider, $overrideRepo, $selectionRepo, $entitlements);
    }

    public function grantsTipChanges(): bool
    {
        return $this->canChangeTips;
    }

    /**
     * @param list<SportMatch> $matches
     */
    private function provideMatches(Competition $competition, array $matches): void
    {
        usort($matches, static fn (SportMatch $a, SportMatch $b): int => $a->kickoffAt <=> $b->kickoffAt);
        $this->providedMatches[$competition->id->toRfc4122()] = $matches;
    }

    private function override(Competition $competition, SportMatch $match, string $deadline): void
    {
        $this->overrides[$competition->id->toRfc4122().'|'.$match->id->toRfc4122()] = new CompetitionMatchSetting(
            id: Uuid::v7(),
            competition: $competition,
            sportMatch: $match,
            deadline: new \DateTimeImmutable($deadline),
            createdAt: $this->now,
        );
    }

    private function select(Competition $competition, SportMatch $match, string $addedAt): void
    {
        $this->selections[$competition->id->toRfc4122()][] = new CompetitionMatchSelection(
            id: Uuid::v7(),
            competition: $competition,
            sportMatch: $match,
            addedAt: new \DateTimeImmutable($addedAt),
        );
    }

    private function makeUser(): User
    {
        $user = new User(
            id: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            email: 'o@test.com',
            password: 'h',
            nickname: 'o',
            createdAt: $this->now,
        );
        $user->popEvents();

        return $user;
    }

    private function makeMatchSource(): MatchSource
    {
        $matchSource = new MatchSource(
            id: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            sport: new Sport(Uuid::fromString(Sport::FOOTBALL_ID), 'football', 'Fotbal', 2, 'poločas', 'poločasy'),
            owner: $this->makeUser(),
            kind: MatchSourceKind::Private,
            name: 'T',
            description: null,
            startAt: null,
            endAt: null,
            createdAt: $this->now,
        );
        $matchSource->popEvents();

        return $matchSource;
    }

    private function makeCompetition(
        CompetitionMatchSelectionMode $selectionMode = CompetitionMatchSelectionMode::All,
        string $createdAt = '2025-06-01 08:00:00 UTC',
    ): Competition {
        $competition = new Competition(
            id: Uuid::v7(),
            matchSource: $this->makeMatchSource(),
            owner: $this->makeUser(),
            name: 'G',
            description: null,
            pin: null,
            shareableLinkToken: null,
            createdAt: new \DateTimeImmutable($createdAt),
            selectionMode: $selectionMode,
        );
        $competition->popEvents();

        return $competition;
    }

    private function makeMatch(Competition $competition, string $kickoff, string $createdAt): SportMatch
    {
        $match = new SportMatch(
            id: Uuid::fromString(sprintf('01933333-0000-7000-8000-0000000009%02d', ++$this->matchSequence)),
            matchSource: $competition->matchSource,
            homeTeam: 'A',
            awayTeam: 'B',
            kickoffAt: new \DateTimeImmutable($kickoff),
            venue: null,
            createdAt: new \DateTimeImmutable($createdAt),
        );
        $match->popEvents();

        return $match;
    }
}
