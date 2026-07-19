<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class InsufficientCredits extends \DomainException
{
    public static function forAdjustment(int $balance, int $amount): self
    {
        return new self(sprintf('Nedostatek kreditů: zůstatek %d nelze snížit o %d.', $balance, abs($amount)));
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
