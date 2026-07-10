<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CreditPurchase;
use App\Enum\CreditPurchaseStatus;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class CreditPurchaseRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CreditPurchase $purchase): void
    {
        $this->entityManager->persist($purchase);
    }

    /**
     * Locks the purchase row so concurrent fulfillment attempts (webhook
     * delivery racing the checkout return page) serialize; the loser of the
     * race then sees the already-completed status and no-ops.
     */
    public function findByCheckoutSessionIdForUpdate(string $sessionId): ?CreditPurchase
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(CreditPurchase::class, 'p')
            ->where('p.stripeCheckoutSessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }

    /**
     * @return CreditPurchase[]
     */
    public function findFiltered(?Uuid $userId, ?CreditPurchaseStatus $status, int $limit): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(CreditPurchase::class, 'p')
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit);

        if (null !== $userId) {
            $qb->andWhere('p.user = :userId')
                ->setParameter('userId', $userId);
        }

        if (null !== $status) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }
}
