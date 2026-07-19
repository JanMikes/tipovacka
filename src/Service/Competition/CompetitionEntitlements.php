<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\Competition;
use App\Entity\User;

/**
 * Per-user premium/boost entitlements within a competition.
 *
 * S07 stub: always answers "no". S10 (premium & boosts commerce) replaces the
 * body with the real resolution — premium toggle `allowTipChanges` ON for the
 * competition, or the user owning an active `tip_change` boost there. Kept as
 * a plain overridable class so the {@see \App\Service\EffectiveTipDeadlineResolver}
 * contract (and its tests) already exercise the entitlement branch.
 */
class CompetitionEntitlements
{
    /**
     * Whether the user may CHANGE tips during the competition („Měnit tip"):
     * tips stay changeable until `Competition::$tipChangeOffsetMinutes` before
     * the day's first competition match.
     */
    public function canChangeTips(Competition $competition, User $user): bool
    {
        return false;
    }
}
