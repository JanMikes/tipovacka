<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(403)]
final class NotAMember extends \DomainException
{
    public static function of(Uuid $competitionId): self
    {
        return new self(sprintf('Nejsi členem soutěže "%s".', $competitionId->toRfc4122()));
    }
}
