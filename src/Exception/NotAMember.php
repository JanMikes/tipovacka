<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(403)]
final class NotAMember extends \DomainException
{
    public static function of(Uuid $groupId): self
    {
        return new self(sprintf('Nejsi členem skupiny "%s".', $groupId->toRfc4122()));
    }
}
