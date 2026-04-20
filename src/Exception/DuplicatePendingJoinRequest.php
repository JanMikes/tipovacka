<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(409)]
final class DuplicatePendingJoinRequest extends \DomainException
{
    public static function forGroup(Uuid $groupId): self
    {
        return new self(sprintf('Pro skupinu "%s" již máš otevřenou žádost.', $groupId->toRfc4122()));
    }
}
