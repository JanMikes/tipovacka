<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

#[WithHttpStatus(409)]
final class CannotJoinFinishedTournament extends \DomainException
{
    public static function forGroup(Uuid $groupId): self
    {
        return new self(sprintf('Nelze se připojit ke skupině "%s" — turnaj je již ukončen.', $groupId->toRfc4122()));
    }
}
