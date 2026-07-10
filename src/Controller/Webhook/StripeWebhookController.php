<?php

declare(strict_types=1);

namespace App\Controller\Webhook;

use App\Command\ExpireCreditPurchase\ExpireCreditPurchaseCommand;
use App\Command\FailCreditPurchase\FailCreditPurchaseCommand;
use App\Command\FulfillCreditPurchase\FulfillCreditPurchaseCommand;
use App\Service\Payment\StripeWebhookParser;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Signature-verified Stripe webhook endpoint. Handlers are idempotent, so
 * duplicate deliveries are harmless; any handler exception bubbles up as
 * a 5xx and Stripe retries the event.
 */
#[Route('/webhooks/stripe', name: 'stripe_webhook', methods: ['POST'])]
final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly StripeWebhookParser $webhookParser,
        private readonly MessageBusInterface $commandBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $event = $this->webhookParser->parse(
            $request->getContent(),
            $request->headers->get('Stripe-Signature', ''),
        );

        if (null === $event->checkoutSessionId) {
            $this->logger->debug('Stripe webhook bez checkout session — ignoruji.', [
                'eventId' => $event->id,
                'eventType' => $event->type,
            ]);

            return new JsonResponse(['received' => true]);
        }

        match ($event->type) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded' => $this->commandBus->dispatch(
                new FulfillCreditPurchaseCommand($event->checkoutSessionId),
            ),
            'checkout.session.async_payment_failed' => $this->commandBus->dispatch(
                new FailCreditPurchaseCommand($event->checkoutSessionId),
            ),
            'checkout.session.expired' => $this->commandBus->dispatch(
                new ExpireCreditPurchaseCommand($event->checkoutSessionId),
            ),
            default => $this->logger->debug('Neobsluhovaný typ Stripe webhooku.', [
                'eventId' => $event->id,
                'eventType' => $event->type,
            ]),
        };

        return new JsonResponse(['received' => true]);
    }
}
