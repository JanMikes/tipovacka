<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\Uid\Uuid;

/**
 * The paid Stripe checkout session does not match our purchase record
 * (amount or currency differs). This must never happen — deliberately
 * maps to a 500 so the webhook is retried and the incident surfaces in Sentry.
 */
final class CreditPurchaseMismatch extends \RuntimeException
{
    public static function forSession(Uuid $purchaseId, string $sessionId, ?int $sessionAmount, ?string $sessionCurrency, int $expectedAmount, string $expectedCurrency): self
    {
        return new self(sprintf(
            'Stripe session "%s" neodpovídá nákupu "%s": zaplaceno %s %s, očekáváno %d %s.',
            $sessionId,
            $purchaseId->toRfc4122(),
            null === $sessionAmount ? 'null' : (string) $sessionAmount,
            $sessionCurrency ?? 'null',
            $expectedAmount,
            $expectedCurrency,
        ));
    }
}
