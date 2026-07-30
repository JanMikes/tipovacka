<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Entity\Competition;
use App\Entity\User;

/**
 * „What if this player OWNED „Počkejte si na sestavy"?" — the one deliberate lie
 * in the entitlement layer, and it exists so that no surface has to re-derive the
 * „Měnit tip" window by hand.
 *
 * A paywall must print the CONCRETE moment the buyer would get back, but
 * {@see \App\Service\EffectiveTipDeadlineResolver} only ever answers for a
 * viewer's REAL entitlements — and a viewer being OFFERED the boost by definition
 * does not hold it. Rather than duplicate „kickoff − tipChangeOffsetMinutes"
 * (which would silently drift the day the resolver's rules move — and they moved
 * twice in July 2026), the resolver is instantiated a SECOND time with this
 * subclass in place of the real {@see CompetitionEntitlements}. Its answer for the
 * same (competition, match, user) is then exactly what the player gets the moment
 * the purchase lands, whatever the resolver's rules happen to be.
 *
 * Wired ONLY into `app.tip_deadline_resolver.tip_change_granted`
 * (config/services.php) and read ONLY through {@see TipChangeUnlock}. Never inject
 * it anywhere else: every other answer is inherited from the real service, but
 * this one is false on purpose.
 */
final class TipChangeGrantedEntitlements extends CompetitionEntitlements
{
    public function canChangeTips(Competition $competition, User $user): bool
    {
        return true;
    }
}
