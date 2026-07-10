<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

#[WithHttpStatus(400)]
final class InvalidStripeWebhook extends \RuntimeException
{
    public static function invalidSignature(\Throwable $previous): self
    {
        return new self('Stripe webhook má neplatný podpis.', 0, $previous);
    }

    public static function malformedPayload(\Throwable $previous): self
    {
        return new self('Stripe webhook má neplatný obsah.', 0, $previous);
    }
}
