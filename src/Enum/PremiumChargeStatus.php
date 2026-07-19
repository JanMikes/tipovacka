<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Lifecycle of a single premium per-player charge
 * ({@see \App\Entity\CompetitionPremiumCharge}). See .docs/DOMAIN.md §Monetization.
 *
 * - Charged: the manager's wallet was successfully debited for this member.
 * - Uncovered: charge attempted at join but the manager's balance was too low —
 *   the member still joined; a later top-up (or re-enable) settles it.
 * - Refunded: credited back to the manager (reconciliation downgrade or the
 *   manager switching the competition to boosts).
 */
enum PremiumChargeStatus: string
{
    case Charged = 'charged';
    case Uncovered = 'uncovered';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Charged => 'Zaplaceno',
            self::Uncovered => 'Nepokryto',
            self::Refunded => 'Vráceno',
        };
    }
}
