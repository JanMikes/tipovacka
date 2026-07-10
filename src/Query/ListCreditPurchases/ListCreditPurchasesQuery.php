<?php

declare(strict_types=1);

namespace App\Query\ListCreditPurchases;

use App\Entity\CreditPurchase;
use App\Repository\CreditPurchaseRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListCreditPurchasesQuery
{
    public function __construct(
        private CreditPurchaseRepository $purchaseRepository,
    ) {
    }

    /**
     * @return list<CreditPurchaseItem>
     */
    public function __invoke(ListCreditPurchases $query): array
    {
        $purchases = $this->purchaseRepository->findFiltered($query->userId, $query->status, $query->limit);

        return array_values(array_map(
            static fn (CreditPurchase $p): CreditPurchaseItem => new CreditPurchaseItem(
                id: $p->id,
                userId: $p->user->id,
                userDisplayName: $p->user->displayName,
                userEmail: $p->user->email,
                credits: $p->credits,
                amountTotal: $p->amountTotal,
                currency: $p->currency,
                status: $p->status,
                stripeCheckoutSessionId: $p->stripeCheckoutSessionId,
                stripePaymentIntentId: $p->stripePaymentIntentId,
                invoiceUrl: $p->stripeInvoiceUrl,
                invoicePdfUrl: $p->stripeInvoicePdfUrl,
                createdAt: $p->createdAt,
                completedAt: $p->completedAt,
            ),
            $purchases,
        ));
    }
}
