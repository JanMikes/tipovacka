<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(422)]
final class InvalidCreditAmount extends \DomainException
{
    public static function belowMinimumPurchase(int $credits, int $minimum): self
    {
        return new self(sprintf('Minimální nákup je %d kreditů, požadováno %d.', $minimum, $credits));
    }

    public static function aboveMaximumPurchase(int $credits, int $maximum): self
    {
        return new self(sprintf('Maximální nákup je %d kreditů, požadováno %d.', $maximum, $credits));
    }

    public static function zeroAdjustment(): self
    {
        return new self('Úprava kreditů nesmí být nulová.');
    }
}
