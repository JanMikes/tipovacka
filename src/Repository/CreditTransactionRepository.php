<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CreditTransaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class CreditTransactionRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CreditTransaction $transaction): void
    {
        $this->entityManager->persist($transaction);
    }

    /**
     * @return CreditTransaction[]
     */
    public function findLatestForUser(Uuid $userId, int $limit): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(CreditTransaction::class, 't')
            ->join('t.wallet', 'w')
            ->where('w.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
