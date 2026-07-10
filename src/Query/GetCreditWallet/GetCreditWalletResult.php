<?php

declare(strict_types=1);

namespace App\Query\GetCreditWallet;

final readonly class GetCreditWalletResult
{
    public function __construct(
        public int $balance,
    ) {
    }
}
