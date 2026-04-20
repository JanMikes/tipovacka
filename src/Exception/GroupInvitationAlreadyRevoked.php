<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class GroupInvitationAlreadyRevoked extends \DomainException
{
    public static function create(): self
    {
        return new self('Pozvánka byla zrušena.');
    }
}
