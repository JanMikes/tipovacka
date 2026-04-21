<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class UserAlreadyPromoted extends \DomainException
{
    public static function forUser(string $nickname): self
    {
        return new self(sprintf('Uživatel "%s" už má přiřazený e-mail.', $nickname));
    }
}
