<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(404)]
final class SportNotFound extends \DomainException
{
    public static function withCode(string $code): self
    {
        return new self(sprintf('Sport s kódem "%s" nebyl nalezen.', $code));
    }

    public static function withId(Uuid $id): self
    {
        return new self(sprintf('Sport s ID "%s" nebyl nalezen.', $id->toRfc4122()));
    }
}
