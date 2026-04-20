<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(404)]
final class InvalidPin extends \DomainException
{
    public static function create(): self
    {
        return new self('Zadaný PIN neexistuje.');
    }
}
