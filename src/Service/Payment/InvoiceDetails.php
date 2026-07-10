<?php

declare(strict_types=1);

namespace App\Service\Payment;

final readonly class InvoiceDetails
{
    public function __construct(
        public string $id,
        public ?string $hostedInvoiceUrl,
        public ?string $invoicePdfUrl,
    ) {
    }
}
