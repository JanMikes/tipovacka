<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(422)]
final class InvalidMatchEvent extends \DomainException
{
    public static function minuteOutOfRange(int $minute): self
    {
        return new self(sprintf('Minuta %d je mimo povolený rozsah 0–150.', $minute));
    }
}
