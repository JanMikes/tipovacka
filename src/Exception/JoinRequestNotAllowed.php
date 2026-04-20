<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(403)]
final class JoinRequestNotAllowed extends \DomainException
{
    public static function privateTournament(): self
    {
        return new self('O připojení lze žádat pouze u veřejných turnajů.');
    }
}
