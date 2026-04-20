<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(400)]
final class InvalidResetToken extends \DomainException
{
    public static function create(): self
    {
        return new self('Token pro obnovení hesla je neplatný nebo vypršel.');
    }
}
