<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\BoostPurchase;
use App\Entity\Competition;
use App\Entity\User;
use App\Enum\BoostType;
use App\Enum\CompetitionMonetization;
use App\Enum\UserRole;
use App\Repository\BoostPurchaseRepository;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The single, DEADLINE-INDEPENDENT authority on a viewer's premium/boost
 * entitlements within a competition. Answers three questions:
 *
 * - {@see canChangeTips} — may the user CHANGE tips during the competition
 *   („Měnit tip")? Consumed by {@see \App\Service\EffectiveTipDeadlineResolver},
 *   which extends the tip deadline for entitled users.
 * - {@see isEntitledToDistribution} / {@see isEntitledToOthersTips} — is the
 *   user entitled to see the anonymous distribution bar / concrete member tips,
 *   REGARDLESS of the tip deadline? The deadline component (post-deadline
 *   everything is public) is composed on top by {@see TipVisibilityGate}.
 *
 * DI-loop avoidance: the resolver injects THIS service (for canChangeTips), so
 * this service must NOT inject the resolver. The deadline-dependent visibility
 * composition therefore lives in {@see TipVisibilityGate} (which injects the
 * resolver), and this class stays purely entitlement logic. See the S10 decision
 * in .docs/DOMAIN.md.
 *
 * Entitlement rules (manager/admin always entitled):
 * - none    → the legacy competition-wide switch: NOT hideOthersTipsBeforeDeadline;
 * - premium → the manager's feature toggles (for everyone);
 * - boosts  → this viewer's own active boost (OthersTips is a superset of the
 *             distribution bar).
 *
 * Per-request cache: the boost-ownership lookup is cached per competition; the
 * kernel reset ({@see ResetInterface}) drops it between requests, and
 * {@see forget} drops one competition after a mutation (purchase / premium
 * enable / switch / settings change).
 */
class CompetitionEntitlements implements ResetInterface
{
    /** @var array<string, array<string, list<BoostType>>> competition UUID → user UUID → owned active boost types */
    private array $ownedBoostsCache = [];

    public function __construct(
        private readonly BoostPurchaseRepository $boostPurchaseRepository,
    ) {
    }

    /**
     * Whether the user may CHANGE tips during the competition („Měnit tip"): true
     * when premium has the toggle on (for everyone), or when the user owns an
     * active `tip_change` boost. DEADLINE-INDEPENDENT by design — it does NOT flip
     * at the competition deadline; it only tells the resolver whether to grant the
     * extended change window.
     *
     * NOTE — unlike the visibility methods below, this does NOT auto-grant to
     * managers/admins. „Uzamknout tipy" and per-match deadlines are a hard,
     * universal freeze (S07): a manager or system admin is locked at competition
     * start just like any member unless the paid „Měnit tip" entitlement is on.
     * A blanket manager/admin grant here would (a) break that freeze for owners
     * and (b) hand every system admin a tip-change window in every competition —
     * neither is intended. See the S10 decision in .docs/DOMAIN.md.
     */
    public function canChangeTips(Competition $competition, User $user): bool
    {
        if (CompetitionMonetization::Premium === $competition->monetization) {
            return $competition->premiumAllowTipChanges;
        }

        if (CompetitionMonetization::Boosts === $competition->monetization) {
            return $this->ownsActiveBoost($competition, $user, BoostType::TipChange);
        }

        return false;
    }

    /**
     * Entitlement to the anonymous distribution bar, ignoring the deadline.
     * OthersTips is a superset — owning it entitles the distribution bar too.
     */
    public function isEntitledToDistribution(Competition $competition, User $user): bool
    {
        if ($this->isManager($competition, $user)) {
            return true;
        }

        return match ($competition->monetization) {
            CompetitionMonetization::None => !$competition->hideOthersTipsBeforeDeadline,
            CompetitionMonetization::Premium => $competition->premiumShowDistribution || $competition->premiumShowOthersTips,
            CompetitionMonetization::Boosts => $this->ownsActiveBoost($competition, $user, BoostType::TipDistribution)
                || $this->ownsActiveBoost($competition, $user, BoostType::OthersTips),
        };
    }

    /**
     * Entitlement to concrete member tips, ignoring the deadline. A superset of
     * {@see isEntitledToDistribution}.
     */
    public function isEntitledToOthersTips(Competition $competition, User $user): bool
    {
        if ($this->isManager($competition, $user)) {
            return true;
        }

        return match ($competition->monetization) {
            CompetitionMonetization::None => !$competition->hideOthersTipsBeforeDeadline,
            CompetitionMonetization::Premium => $competition->premiumShowOthersTips,
            CompetitionMonetization::Boosts => $this->ownsActiveBoost($competition, $user, BoostType::OthersTips),
        };
    }

    public function forget(Uuid $competitionId): void
    {
        unset($this->ownedBoostsCache[$competitionId->toRfc4122()]);
    }

    public function reset(): void
    {
        $this->ownedBoostsCache = [];
    }

    private function isManager(Competition $competition, User $user): bool
    {
        return in_array(UserRole::ADMIN->value, $user->getRoles(), true)
            || $user->id->equals($competition->owner->id);
    }

    private function ownsActiveBoost(Competition $competition, User $user, BoostType $type): bool
    {
        return in_array($type, $this->ownedBoostTypes($competition, $user), true);
    }

    /**
     * @return list<BoostType>
     */
    private function ownedBoostTypes(Competition $competition, User $user): array
    {
        $competitionKey = $competition->id->toRfc4122();
        $userKey = $user->id->toRfc4122();

        if (!isset($this->ownedBoostsCache[$competitionKey][$userKey])) {
            $this->ownedBoostsCache[$competitionKey][$userKey] = array_map(
                static fn (BoostPurchase $purchase): BoostType => $purchase->type,
                $this->boostPurchaseRepository->findActiveByUserAndCompetition($user->id, $competition->id),
            );
        }

        return $this->ownedBoostsCache[$competitionKey][$userKey];
    }
}
