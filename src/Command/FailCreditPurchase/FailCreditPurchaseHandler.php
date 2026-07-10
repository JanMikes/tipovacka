<?php

declare(strict_types=1);

namespace App\Command\FailCreditPurchase;

use App\Repository\CreditPurchaseRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FailCreditPurchaseHandler
{
    public function __construct(
        private CreditPurchaseRepository $purchaseRepository,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(FailCreditPurchaseCommand $command): void
    {
        $purchase = $this->purchaseRepository->findByCheckoutSessionIdForUpdate($command->checkoutSessionId);

        if (null === $purchase) {
            $this->logger->info('Ignoruji neúspěšnou platbu Stripe session bez odpovídajícího nákupu.', [
                'sessionId' => $command->checkoutSessionId,
            ]);

            return;
        }

        if (!$purchase->isPending) {
            return;
        }

        $purchase->markFailed($this->clock->now());
    }
}
