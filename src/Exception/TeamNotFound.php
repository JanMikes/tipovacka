<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(404)]
final class TeamNotFound extends \DomainException
{
    public static function withId(Uuid $id): self
    {
        return new self(sprintf('Tým s ID "%s" nebyl nalezen.', $id->toRfc4122()));
    }

    public static function withName(string $name): self
    {
        return new self(sprintf('Tým se jménem "%s" nebyl nalezen.', $name));
    }
}
