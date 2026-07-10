<?php

declare(strict_types=1);

namespace App\Command\ExpireCreditPurchase;

use App\Repository\CreditPurchaseRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ExpireCreditPurchaseHandler
{
    public function __construct(
        private CreditPurchaseRepository $purchaseRepository,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExpireCreditPurchaseCommand $command): void
    {
        $purchase = $this->purchaseRepository->findByCheckoutSessionIdForUpdate($command->checkoutSessionId);

        if (null === $purchase) {
            $this->logger->info('Ignoruji expiraci Stripe session bez odpovídajícího nákupu.', [
                'sessionId' => $command->checkoutSessionId,
            ]);

            return;
        }

        if (!$purchase->isPending) {
            return;
        }

        $purchase->markExpired($this->clock->now());
    }
}
