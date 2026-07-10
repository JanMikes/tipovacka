<?php

declare(strict_types=1);

namespace App\Query\GetCreditWallet;

use App\Repository\CreditWalletRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCreditWalletQuery
{
    public function __construct(
        private CreditWalletRepository $walletRepository,
    ) {
    }

    public function __invoke(GetCreditWallet $query): GetCreditWalletResult
    {
        $wallet = $this->walletRepository->findByUserId($query->userId);

        return new GetCreditWalletResult(
            balance: $wallet->balance ?? 0,
        );
    }
}
