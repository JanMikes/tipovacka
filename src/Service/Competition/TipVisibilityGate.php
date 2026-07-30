<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Service\EffectiveTipDeadlineResolver;
use Psr\Clock\ClockInterface;

/**
 * Composes the two independent halves of tip visibility into a single per-viewer,
 * per-match answer:
 *
 *   see anyone's tips iff  (viewer is ENTITLED)  OR  (the match HAS A FINAL RESULT)
 *
 * - The ENTITLEMENT is per-viewer ({@see CompetitionEntitlements}) — a premium
 *   toggle the organizer switched on for everyone, or THIS viewer's own boost. So
 *   a viewer who bought the OthersTips boost sees others' tips while the match is
 *   still ahead, and others do not. Managers and admins get NO free pass.
 * - The FREE half is the match's RESULT, not its deadline: once a final score is
 *   entered („odehráno" = `SportMatchState::Finished`) the tips are public to
 *   everyone, because there is nothing left to copy.
 *
 * **The deadline is deliberately NOT part of this decision** (2026-07-30, ui-nav
 * item 20 — it used to be „entitled OR past deadline"). A schedule is an
 * intention; a result is a fact. Two ways the old rule leaked a paid sight:
 *
 * - a kickoff passes and the match is NOT played (an organizer late to postpone) —
 *   the deadline is behind us, so every tip became readable for a match still to
 *   be played;
 * - a late-added match follows its own kickoff ({@see EffectiveTipDeadlineResolver}
 *   decision row 2), so postpone-then-reschedule could reopen tipping AFTER such a
 *   reveal already happened — copyable tips, by accident of admin timing.
 *
 * An unplayed match has no score whatever its schedule says, so the result test
 * closes both holes and needs no ops discipline to stay closed. It applies to the
 * concrete tips AND to the anonymous 1 / X / 2 distribution — one rule for every
 * tip-revealing surface. See .docs/DOMAIN.md §Tips visibility.
 *
 * The decision is **explicitly revisitable through ONE knob**,
 * {@see $freeRevealRequiresResult} (wired in config/services.php, same house
 * pattern as `CompetitionEntitlements::$managersSeeTipsForFree`): set it to false
 * and every surface goes back to the deadline reveal. That is why the deadline
 * resolver is still injected here. This service injects the resolver;
 * {@see CompetitionEntitlements} must not (it is injected BY the resolver). That
 * split is what keeps the container acyclic.
 */
final readonly class TipVisibilityGate
{
    /**
     * @param bool $freeRevealRequiresResult the ONE knob behind „when do other players'
     *                                       tips become free to read?". true (default,
     *                                       since 2026-07-30) = only once the match has a
     *                                       FINAL RESULT; false = the pre-2026-07-30
     *                                       behaviour, where a passed tip deadline was
     *                                       enough. It governs the concrete tips and the
     *                                       distribution alike, so the two can never
     *                                       disagree, and NO other place in the app may
     *                                       compare `now` against a deadline to decide
     *                                       visibility. Wired explicitly in
     *                                       config/services.php so reverting the decision
     *                                       is a one-line change.
     */
    public function __construct(
        private CompetitionEntitlements $entitlements,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ClockInterface $clock,
        private bool $freeRevealRequiresResult = true,
    ) {
    }

    public function canSeeDistribution(Competition $competition, ?User $viewer, SportMatch $sportMatch): bool
    {
        return (null !== $viewer && $this->entitlements->isEntitledToDistribution($competition, $viewer))
            || $this->revealedWithoutEntitlement($competition, $sportMatch);
    }

    public function canSeeOthersTips(Competition $competition, ?User $viewer, SportMatch $sportMatch): bool
    {
        return (null !== $viewer && $this->entitlements->isEntitledToOthersTips($competition, $viewer))
            || $this->revealedWithoutEntitlement($competition, $sportMatch);
    }

    /**
     * Batch variant for whole pages (the guess matrix, every match list): whether
     * $viewer may see OTHERS' concrete tips for each match. The entitlement half is
     * constant per (competition, viewer) — computed once — and OR-ed with each
     * match's own free-reveal answer.
     *
     * @param list<SportMatch> $matches
     *
     * @return array<string, bool> keyed by sport match id RFC4122
     */
    public function othersTipsVisibleByMatch(Competition $competition, ?User $viewer, array $matches): array
    {
        $entitled = null !== $viewer && $this->entitlements->isEntitledToOthersTips($competition, $viewer);

        return $this->visibleByMatch($competition, $entitled, $matches);
    }

    /**
     * Batch variant of {@see canSeeDistribution} for the „Rozložení tipů" surface
     * of a whole page ({@see TipStatsProvider}) — the same free-reveal rule, so the
     * aggregate can never open earlier than the tips it aggregates.
     *
     * @param list<SportMatch> $matches
     *
     * @return array<string, bool> keyed by sport match id RFC4122
     */
    public function distributionVisibleByMatch(Competition $competition, ?User $viewer, array $matches): array
    {
        $entitled = null !== $viewer && $this->entitlements->isEntitledToDistribution($competition, $viewer);

        return $this->visibleByMatch($competition, $entitled, $matches);
    }

    /**
     * @param list<SportMatch> $matches
     *
     * @return array<string, bool> keyed by sport match id RFC4122
     */
    private function visibleByMatch(Competition $competition, bool $entitled, array $matches): array
    {
        if ($entitled) {
            return array_fill_keys(
                array_map(static fn (SportMatch $match): string => $match->id->toRfc4122(), $matches),
                true,
            );
        }

        // Batch the deadlines ONLY on the deadline setting (one query per
        // competition instead of one per match); the result rule needs no query.
        $deadlines = $this->freeRevealRequiresResult
            ? []
            : $this->deadlineResolver->deadlinesFor($competition, $matches);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $result = [];

        foreach ($matches as $match) {
            $key = $match->id->toRfc4122();

            if ($this->freeRevealRequiresResult) {
                $result[$key] = $match->isFinished;

                continue;
            }

            $deadline = $deadlines[$key] ?? null;
            $result[$key] = null !== $deadline && $now >= $deadline;
        }

        return $result;
    }

    /**
     * THE one place the „free" (entitlement-less) half of tip visibility is decided,
     * for concrete tips and the distribution alike. Never inline this comparison
     * anywhere else — that is how two surfaces start disagreeing.
     */
    private function revealedWithoutEntitlement(Competition $competition, SportMatch $sportMatch): bool
    {
        if ($this->freeRevealRequiresResult) {
            return $sportMatch->isFinished;
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        // Userless deadline — visibility is competition-wide, never per viewer.
        return $now >= $this->deadlineResolver->deadlineFor($competition, $sportMatch);
    }
}
