<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(409)]
final class CreditPurchaseRequiresEmail extends \DomainException
{
    public static function forAnonymousAccount(): self
    {
        return new self('Kredity může nakupovat jen účet s e-mailem.');
    }
}
