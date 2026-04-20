<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class GroupJoinRequestAlreadyDecided extends \DomainException
{
    public static function create(): self
    {
        return new self('O této žádosti o připojení již bylo rozhodnuto.');
    }
}
