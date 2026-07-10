<?php

declare(strict_types=1);

namespace App\Service\Credits;

use App\Entity\CreditWallet;
use App\Entity\User;
use App\Repository\CreditWalletRepository;
use App\Service\Identity\ProvideIdentity;

/**
 * Wallets are created lazily on the first credit movement. If two concurrent
 * first movements race, the unique constraint on user_id fails one transaction;
 * the caller (Stripe webhook) retries and finds the wallet on the next attempt.
 */
final readonly class CreditWalletProvider
{
    public function __construct(
        private CreditWalletRepository $walletRepository,
        private ProvideIdentity $identity,
    ) {
    }

    public function getOrCreate(User $user, \DateTimeImmutable $now): CreditWallet
    {
        $wallet = $this->walletRepository->findByUserId($user->id);

        if (null !== $wallet) {
            return $wallet;
        }

        return $this->create($user, $now);
    }

    public function getForUpdateOrCreate(User $user, \DateTimeImmutable $now): CreditWallet
    {
        $wallet = $this->walletRepository->findByUserIdForUpdate($user->id);

        if (null !== $wallet) {
            return $wallet;
        }

        return $this->create($user, $now);
    }

    private function create(User $user, \DateTimeImmutable $now): CreditWallet
    {
        $wallet = new CreditWallet(
            id: $this->identity->next(),
            user: $user,
            createdAt: $now,
        );

        $this->walletRepository->save($wallet);

        return $wallet;
    }
}
