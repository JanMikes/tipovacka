<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Repository\CompetitionMatchSelectionRepository;
use App\Repository\CompetitionMatchSettingRepository;
use App\Service\Competition\CompetitionEntitlements;
use App\Service\Competition\CompetitionMatchProvider;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * THE single authority answering "until when may (user U) tip match M in
 * competition C". No other surface may compare kickoffs/deadlines on its own —
 * callers only compare `now` against the returned deadline (or use
 * {@see isLocked}, which additionally respects `SportMatch::$isOpenForGuesses`).
 *
 * Model (DOMAIN.md §Tip locking): tips lock at the competition's start — the
 * earliest kickoff among its included matches, or earlier via the manager's
 * manual „Uzamknout tipy" (`Competition::$tipsLockedAt`). Matches entering the
 * competition after that lock moment (late playoff additions) stay tippable
 * until their own kickoff; managers may override per match; the „Měnit tip"
 * entitlement extends the window to shortly before the day's first match.
 *
 * Decision table for deadlineFor(C, M, U) — first matching row wins, then the
 * entitlement row may only EXTEND the result (a paid boost never shortens a
 * window, and explicit manager decisions stay in force when more generous):
 *
 * | # | Condition                                                       | Deadline                          |
 * |---|-----------------------------------------------------------------|-----------------------------------|
 * | 1 | manager per-match override (CompetitionMatchSetting) exists     | min(override, M.kickoffAt)        |
 * | 2 | M is late-added — mode All: max(M.createdAt, C.createdAt) >      | M.kickoffAt                       |
 * |   | lock moment; mode Subset: selection.addedAt > lock moment       |                                   |
 * | 3 | default                                                         | min(lock moment, M.kickoffAt)     |
 * | + | U given and CompetitionEntitlements::canChangeTips(C, U)        | max(above, min(dayFirstKickoff −  |
 * |   |                                                                 | C.tipChangeOffsetMinutes, kickoff))|
 *
 * where
 * - lock moment = C.tipsLockedAt ?? earliest kickoffAt among C's included
 *   matches (via {@see CompetitionMatchProvider}, computed live — any state,
 *   deleted excluded); a competition without matches has no lock moment and
 *   falls back to M's kickoff;
 * - dayFirstKickoff = earliest kickoffAt among C's included matches on M's
 *   Europe/Prague calendar day (kickoffs are stored UTC and converted to
 *   Europe/Prague for the day grouping — a 23:30 UTC kickoff belongs to the
 *   NEXT Prague day). M itself is included, so the set is never empty.
 *
 * Invariant: the returned deadline is never later than M's kickoffAt — every
 * row above is capped by it (row 2 trivially; the entitlement term because the
 * day's first kickoff ≤ M's kickoff and the offset is non-negative).
 *
 * Postponement: moving a kickoff does NOT reopen tipping. A non-late-added
 * match keeps deadline = lock moment (row 3) no matter where its kickoff moves;
 * only the kickoff cap tracks the NEW kickoff (relevant when a match moves
 * EARLIER than a stored override). A late-added match follows its own (new)
 * kickoff by row 2 — while postponed, `SportMatch::$isOpenForGuesses` is false
 * anyway, so tipping resumes only once the match is rescheduled.
 *
 * Lock-defining match leaving (postpone/soft-delete): when the automatic lock
 * moment is defined by a match's kickoff and THAT match is postponed later or
 * deleted after the moment already passed, the live first-kickoff would jump
 * forward and reopen closed tips. The SportMatchPostponed / SportMatchDeleted
 * handlers prevent this by pinning `Competition::$tipsLockedAt` to the pinned
 * moment via {@see lockMomentToPinAfterDefiningMatchLeft} — the lock moment can
 * only ever be reached once.
 *
 * Visibility is competition-wide, never per viewer: whether OTHERS' tips are
 * revealed is gated on the USERLESS deadline `deadlineFor(C, M)` (what all the
 * competition's embedded ranking/distribution queries assume). A viewer's own
 * „Měnit tip" entitlement extends only when THEY may still tip/change — it
 * never changes what they may SEE of other members' tips.
 *
 * Per-request caching: the included-match list (→ first kickoff, day-first
 * lookups) and Subset selection addedAt map are cached per competition; the
 * kernel reset ({@see ResetInterface}) drops them between requests. Long-lived
 * processes mutating matches/selections mid-flight can call
 * {@see forgetCompetition}.
 */
class EffectiveTipDeadlineResolver implements ResetInterface
{
    private const string PRAGUE_TIMEZONE = 'Europe/Prague';

    /** @var array<string, list<SportMatch>> competition UUID → included matches, kickoff-ordered */
    private array $matchesCache = [];

    /** @var array<string, array<string, \DateTimeImmutable>> competition UUID → selected match UUID → addedAt */
    private array $selectionAddedAtCache = [];

    public function __construct(
        private readonly CompetitionMatchProvider $matchProvider,
        private readonly CompetitionMatchSettingRepository $overrideRepository,
        private readonly CompetitionMatchSelectionRepository $selectionRepository,
        private readonly CompetitionEntitlements $entitlements,
    ) {
    }

    public function deadlineFor(Competition $competition, SportMatch $sportMatch, ?User $user = null): \DateTimeImmutable
    {
        $override = $this->overrideRepository->findByCompetitionAndMatch($competition->id, $sportMatch->id);

        return $this->computeDeadline($competition, $sportMatch, $override?->deadline, $user);
    }

    /**
     * Batch variant of {@see deadlineFor} (one override query for all matches).
     *
     * @param list<SportMatch> $matches
     *
     * @return array<string, \DateTimeImmutable> keyed by sport match id RFC4122
     */
    public function deadlinesFor(Competition $competition, array $matches, ?User $user = null): array
    {
        if ([] === $matches) {
            return [];
        }

        $matchIds = array_map(static fn (SportMatch $m): Uuid => $m->id, $matches);
        $overrides = $this->overrideRepository->findByCompetitionAndMatches($competition->id, $matchIds);

        $result = [];

        foreach ($matches as $match) {
            $key = $match->id->toRfc4122();
            $result[$key] = $this->computeDeadline($competition, $match, ($overrides[$key] ?? null)?->deadline, $user);
        }

        return $result;
    }

    /**
     * Convenience gate: whether tipping M in C is closed for U at $now. Beyond
     * the deadline comparison this also respects the match state — a match that
     * is not open for guesses (live/finished/postponed/cancelled/deleted) is
     * always locked, whatever its deadline.
     */
    public function isLocked(Competition $competition, SportMatch $sportMatch, ?User $user, \DateTimeImmutable $now): bool
    {
        if (!$sportMatch->isOpenForGuesses) {
            return true;
        }

        return $now >= $this->deadlineFor($competition, $sportMatch, $user);
    }

    /**
     * The competition-level lock moment: the manual `tipsLockedAt` when set,
     * else the earliest kickoff among included matches (null when the
     * competition has no matches). This is what the competition detail hero
     * shows as „Uzávěrka tipů" while tipping is still open.
     */
    public function lockMomentFor(Competition $competition): ?\DateTimeImmutable
    {
        return $competition->tipsLockedAt ?? $this->firstKickoffFor($competition);
    }

    /**
     * Earliest kickoff among the competition's included matches (any state),
     * null when it has none. Also the moment after which a manual lock can no
     * longer be undone.
     */
    public function firstKickoffFor(Competition $competition): ?\DateTimeImmutable
    {
        $matches = $this->includedMatches($competition);

        return [] === $matches ? null : $matches[0]->kickoffAt;
    }

    /**
     * Correctness pin for the automatic lock moment. When the match that
     * DEFINED a competition's lock moment (its earliest included kickoff)
     * leaves that role — postponed to later, or soft-deleted — AFTER the lock
     * moment had already been reached, the naive live recomputation would pick
     * a later kickoff and silently REOPEN tips that were already closed.
     *
     * Returns the moment to pin onto {@see Competition::$tipsLockedAt}, or null
     * when no pin is needed. Call from the SportMatchPostponed /
     * SportMatchDeleted handlers AFTER the change is persisted, passing the
     * match's PRE-CHANGE kickoff; drops the competition cache so the post-change
     * first kickoff is read fresh.
     */
    public function lockMomentToPinAfterDefiningMatchLeft(
        Competition $competition,
        SportMatch $match,
        \DateTimeImmutable $previousKickoff,
        \DateTimeImmutable $now,
    ): ?\DateTimeImmutable {
        // Already locked/pinned (manual lock or a prior pin) ⇒ the lock moment
        // is fixed; nothing to protect.
        if (null !== $competition->tipsLockedAt) {
            return null;
        }

        // The lock moment (reached via THIS kickoff) had not arrived yet ⇒ the
        // competition has not started; recomputing/reopening is legitimate.
        if ($previousKickoff > $now) {
            return null;
        }

        // The match must actually belong to the competition — reason about the
        // PRE-change membership, ignoring the now-set deletedAt.
        if (!$this->matchProvider->includesIgnoringDeletion($competition, $match)) {
            return null;
        }

        $this->forgetCompetition($competition->id);

        // Was this kickoff the competition's lock-defining first? It is iff it
        // is not later than every remaining included match (null = none remain).
        $newFirstKickoff = $this->firstKickoffFor($competition);

        if (null !== $newFirstKickoff && $previousKickoff > $newFirstKickoff) {
            return null;
        }

        return $previousKickoff;
    }

    public function forgetCompetition(Uuid $competitionId): void
    {
        $key = $competitionId->toRfc4122();
        unset($this->matchesCache[$key], $this->selectionAddedAtCache[$key]);
    }

    /**
     * Kernel reset (autoconfigured via {@see ResetInterface}) — drops the
     * per-request caches so stale match lists never leak between requests.
     */
    public function reset(): void
    {
        $this->matchesCache = [];
        $this->selectionAddedAtCache = [];
    }

    private function computeDeadline(
        Competition $competition,
        SportMatch $sportMatch,
        ?\DateTimeImmutable $overrideDeadline,
        ?User $user,
    ): \DateTimeImmutable {
        $kickoff = $sportMatch->kickoffAt;

        if (null !== $overrideDeadline) {
            // Row 1 — the override was validated ≤ kickoff at write time, but a
            // later postponement may have moved the kickoff EARLIER: cap again.
            $base = min($overrideDeadline, $kickoff);
        } else {
            $lockMoment = $this->lockMomentFor($competition);

            if (null === $lockMoment) {
                // Defensive: competition without matches — nothing to lock on.
                $base = $kickoff;
            } elseif ($this->isLateAdded($competition, $sportMatch, $lockMoment)) {
                // Row 2 — entered the competition after its lock moment.
                $base = $kickoff;
            } else {
                // Row 3 — locked at competition start (or manual lock).
                $base = min($lockMoment, $kickoff);
            }
        }

        if (null === $user || !$this->entitlements->canChangeTips($competition, $user)) {
            return $base;
        }

        $entitled = $this->entitledDeadline($competition, $sportMatch);

        return max($base, min($entitled, $kickoff));
    }

    private function isLateAdded(Competition $competition, SportMatch $sportMatch, \DateTimeImmutable $lockMoment): bool
    {
        if (CompetitionMatchSelectionMode::Subset === $competition->selectionMode) {
            $addedAt = $this->selectionAddedAtMap($competition)[$sportMatch->id->toRfc4122()] ?? null;

            // No selection row ⇒ the match is not in the competition at all;
            // callers guard with MatchNotInCompetition, so just fall through
            // to the default branch here.
            return null !== $addedAt && $addedAt > $lockMoment;
        }

        // A match "enters" an All-mode competition at the LATER of its own
        // creation and the competition's creation: a competition created after
        // the source's first kickoff must treat every pre-existing match as
        // late-added (tippable until its own kickoff), never as pre-lock. This
        // mirrors Subset, whose initial selections get addedAt = competition
        // creation — so both modes agree on when a match became the competition's.
        $enteredAt = max($sportMatch->createdAt, $competition->createdAt);

        return $enteredAt > $lockMoment;
    }

    /**
     * „Měnit tip" window end: kickoff of the day's first included competition
     * match (Europe/Prague day of $sportMatch's kickoff) minus the
     * competition's tip-change offset.
     */
    private function entitledDeadline(Competition $competition, SportMatch $sportMatch): \DateTimeImmutable
    {
        $pragueDay = $this->pragueDay($sportMatch->kickoffAt);
        $dayFirstKickoff = $sportMatch->kickoffAt;

        foreach ($this->includedMatches($competition) as $match) {
            if ($this->pragueDay($match->kickoffAt) !== $pragueDay) {
                continue;
            }

            // Included matches are kickoff-ordered — the first hit is the day's first.
            $dayFirstKickoff = $match->kickoffAt;

            break;
        }

        return $dayFirstKickoff->modify(sprintf('-%d minutes', $competition->tipChangeOffsetMinutes));
    }

    private function pragueDay(\DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new \DateTimeZone(self::PRAGUE_TIMEZONE))->format('Y-m-d');
    }

    /**
     * @return list<SportMatch> kickoff-ordered
     */
    private function includedMatches(Competition $competition): array
    {
        $key = $competition->id->toRfc4122();

        return $this->matchesCache[$key] ??= $this->matchProvider->matchesFor($competition);
    }

    /**
     * @return array<string, \DateTimeImmutable> selected match UUID → addedAt
     */
    private function selectionAddedAtMap(Competition $competition): array
    {
        $key = $competition->id->toRfc4122();

        if (!isset($this->selectionAddedAtCache[$key])) {
            $map = [];

            foreach ($this->selectionRepository->listByCompetition($competition->id) as $selection) {
                $map[$selection->sportMatch->id->toRfc4122()] = $selection->addedAt;
            }

            $this->selectionAddedAtCache[$key] = $map;
        }

        return $this->selectionAddedAtCache[$key];
    }
}
