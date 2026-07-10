<?php

declare(strict_types=1);

namespace App\Command\InitiateCreditPurchase;

use Symfony\Component\Uid\Uuid;

final readonly class InitiateCreditPurchaseCommand
{
    /** Below ~100 CZK the payment fees eat the margin. */
    public const int MINIMUM_CREDITS = 100;
    public const int MAXIMUM_CREDITS = 100000;

    public function __construct(
        public Uuid $userId,
        public int $credits,
        public string $successUrl,
        public string $cancelUrl,
    ) {
    }
}
