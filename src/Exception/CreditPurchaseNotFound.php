<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(404)]
final class CreditPurchaseNotFound extends \RuntimeException
{
    public static function withCheckoutSessionId(string $sessionId): self
    {
        return new self(sprintf('Nákup kreditů pro Stripe checkout session "%s" nebyl nalezen.', $sessionId));
    }
}
