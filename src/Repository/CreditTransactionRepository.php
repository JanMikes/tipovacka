<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CreditTransaction;
use App\Enum\CreditTransactionType;
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

    /**
     * Admin-wide ledger, newest first, optionally filtered by transaction type
     * and/or the referenced competition. Wallet owner is fetch-joined so the
     * admin view can name whose balance moved without an N+1.
     *
     * @return list<CreditTransaction>
     */
    public function findLatest(?CreditTransactionType $type, ?Uuid $competitionId, int $limit): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('t', 'w', 'u')
            ->from(CreditTransaction::class, 't')
            ->join('t.wallet', 'w')
            ->join('w.user', 'u')
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setMaxResults($limit);

        if (null !== $type) {
            $qb->andWhere('t.type = :type')->setParameter('type', $type);
        }

        if (null !== $competitionId) {
            $qb->andWhere('t.competition = :competitionId')->setParameter('competitionId', $competitionId);
        }

        /** @var list<CreditTransaction> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Distinct competitions referenced by at least one credit transaction —
     * the competition filter options for the admin ledger.
     *
     * @return list<array{id: Uuid, name: string}>
     */
    public function findReferencedCompetitions(): array
    {
        /** @var list<array{id: Uuid, name: string}> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT c.id AS id, c.name AS name')
            ->from(CreditTransaction::class, 't')
            ->join('t.competition', 'c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
