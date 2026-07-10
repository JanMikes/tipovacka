<?php

declare(strict_types=1);

namespace App\Command\AdjustUserCredits;

use App\Entity\CreditTransaction;
use App\Repository\CreditTransactionRepository;
use App\Repository\UserRepository;
use App\Service\Credits\CreditWalletProvider;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AdjustUserCreditsHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private CreditTransactionRepository $transactionRepository,
        private CreditWalletProvider $walletProvider,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AdjustUserCreditsCommand $command): CreditTransaction
    {
        $user = $this->userRepository->get($command->userId);
        $adjustedBy = $this->userRepository->get($command->adjustedById);

        $now = $this->clock->now();
        $wallet = $this->walletProvider->getForUpdateOrCreate($user, $now);

        $transaction = $wallet->adjustByAdmin(
            transactionId: $this->identity->next(),
            amount: $command->amount,
            note: $command->note,
            adjustedBy: $adjustedBy,
            now: $now,
        );

        $this->transactionRepository->save($transaction);

        return $transaction;
    }
}
