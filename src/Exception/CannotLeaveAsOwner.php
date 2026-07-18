<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(409)]
final class CannotLeaveAsOwner extends \DomainException
{
    public static function of(Uuid $competitionId): self
    {
        return new self(sprintf('Vlastník nemůže opustit vlastní soutěž "%s".', $competitionId->toRfc4122()));
    }
}
