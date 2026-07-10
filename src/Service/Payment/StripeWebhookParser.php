<?php

declare(strict_types=1);

namespace App\Service\Payment;

use App\Exception\InvalidStripeWebhook;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Verifies the Stripe-Signature header against the endpoint signing secret
 * and extracts the minimum the app needs. Deliberately separate from
 * PaymentGateway so tests exercise real signature verification while the
 * gateway itself is faked.
 */
final readonly class StripeWebhookParser
{
    public function __construct(#[\SensitiveParameter] private string $webhookSecret)
    {
    }

    public function parse(string $payload, string $signatureHeader): WebhookEvent
    {
        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $this->webhookSecret);
        } catch (SignatureVerificationException $e) {
            throw InvalidStripeWebhook::invalidSignature($e);
        } catch (\UnexpectedValueException $e) {
            throw InvalidStripeWebhook::malformedPayload($e);
        }

        $object = $event->data->object ?? null;

        return new WebhookEvent(
            id: $event->id,
            type: $event->type,
            checkoutSessionId: $object instanceof Session ? $object->id : null,
        );
    }
}
