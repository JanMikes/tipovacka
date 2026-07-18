<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(409)]
final class AlreadyMember extends \DomainException
{
    public static function in(Uuid $competitionId): self
    {
        return new self(sprintf('Již jsi členem soutěže "%s".', $competitionId->toRfc4122()));
    }
}
