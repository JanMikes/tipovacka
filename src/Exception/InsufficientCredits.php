<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class InsufficientCredits extends \DomainException
{
    public static function forAdjustment(int $balance, int $amount): self
    {
        // Read by the admin on the credit-adjustment form, so it says how far they CAN go.
        return new self(sprintf(
            'Nedostatek kreditů: uživatel má na účtu %d, odebrat mu jich %d nelze. Odeberte nejvýše %d.',
            $balance,
            abs($amount),
            max(0, $balance),
        ));
    }

    public static function forSpend(int $missing): self
    {
        return new self(sprintf('Nedostatek kreditů — do potřebné částky chybí %d.', $missing));
    }

    /**
     * Re-enabling premium charges the whole group at once (all-or-nothing), so
     * the message names the exact total the manager needs.
     */
    public static function forPremiumActivation(int $totalNeeded, int $balance): self
    {
        return new self(sprintf(
            'Nedostatek kreditů pro zapnutí prémia: potřebujete %d kreditů, na účtu máte %d.',
            $totalNeeded,
            $balance,
        ));
    }
}
