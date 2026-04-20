<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(404)]
final class InvalidInvitationToken extends \DomainException
{
    public static function forToken(string $token): self
    {
        return new self(sprintf('Pozvánkový token "%s" není platný.', $token));
    }
}
