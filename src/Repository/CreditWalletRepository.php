<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CreditWallet;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class CreditWalletRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CreditWallet $wallet): void
    {
        $this->entityManager->persist($wallet);
    }

    public function findByUserId(Uuid $userId): ?CreditWallet
    {
        return $this->entityManager->createQueryBuilder()
            ->select('w')
            ->from(CreditWallet::class, 'w')
            ->where('w.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Locks the wallet row (SELECT ... FOR UPDATE) so concurrent credit
     * movements — e.g. a webhook retry racing the checkout return page —
     * serialize instead of double-crediting. Requires an open transaction
     * (provided by the doctrine_transaction command-bus middleware).
     */
    public function findByUserIdForUpdate(Uuid $userId): ?CreditWallet
    {
        return $this->entityManager->createQueryBuilder()
            ->select('w')
            ->from(CreditWallet::class, 'w')
            ->where('w.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }
}
