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
}
