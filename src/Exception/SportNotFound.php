<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(404)]
final class SportNotFound extends \DomainException
{
    public static function withCode(string $code): self
    {
        return new self(sprintf('Sport s kódem "%s" nebyl nalezen.', $code));
    }
}
