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
 *   see anyone's tips iff  (viewer is ENTITLED)  OR  (the match's deadline passed)
 *
 * - The ENTITLEMENT is per-viewer ({@see CompetitionEntitlements}) — a manager,
 *   a premium toggle for everyone, or THIS viewer's own boost. So a viewer who
 *   bought the OthersTips boost sees others' tips before the deadline while
 *   others do not.
 * - The DEADLINE component is competition-wide / userless
 *   ({@see EffectiveTipDeadlineResolver::deadlineFor} without a user): after a
 *   match's generic effective deadline everything is public to everyone. A
 *   viewer's own „Měnit tip" entitlement extends only when THEY may still tip —
 *   it never reveals other members' tips early (see .docs/DOMAIN.md §Tip
 *   visibility, 2026-07-19 decision).
 *
 * This service injects the resolver; {@see CompetitionEntitlements} must not (it
 * is injected BY the resolver). That split is what keeps the container acyclic.
 */
final readonly class TipVisibilityGate
{
    public function __construct(
        private CompetitionEntitlements $entitlements,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ClockInterface $clock,
    ) {
    }

    public function canSeeDistribution(Competition $competition, ?User $viewer, SportMatch $sportMatch): bool
    {
        return (null !== $viewer && $this->entitlements->isEntitledToDistribution($competition, $viewer))
            || $this->isPastDeadline($competition, $sportMatch);
    }

    public function canSeeOthersTips(Competition $competition, ?User $viewer, SportMatch $sportMatch): bool
    {
        return (null !== $viewer && $this->entitlements->isEntitledToOthersTips($competition, $viewer))
            || $this->isPastDeadline($competition, $sportMatch);
    }

    /**
     * Batch variant for the guess matrix: whether $viewer may see OTHERS' concrete
     * tips for each match. The entitlement half is constant per (competition,
     * viewer) — computed once — and OR-ed with each match's userless deadline.
     *
     * @param list<SportMatch> $matches
     *
     * @return array<string, bool> keyed by sport match id RFC4122
     */
    public function othersTipsVisibleByMatch(Competition $competition, ?User $viewer, array $matches): array
    {
        $entitled = null !== $viewer && $this->entitlements->isEntitledToOthersTips($competition, $viewer);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $deadlines = $this->deadlineResolver->deadlinesFor($competition, $matches);

        $result = [];

        foreach ($matches as $match) {
            $key = $match->id->toRfc4122();
            $deadline = $deadlines[$key] ?? null;
            $result[$key] = $entitled || (null !== $deadline && $now >= $deadline);
        }

        return $result;
    }

    private function isPastDeadline(Competition $competition, SportMatch $sportMatch): bool
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        // Userless deadline — visibility is competition-wide, never per viewer.
        return $now >= $this->deadlineResolver->deadlineFor($competition, $sportMatch);
    }
}
