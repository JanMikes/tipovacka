<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BoostPurchase;
use App\Enum\BoostType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class BoostPurchaseRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(BoostPurchase $boostPurchase): void
    {
        $this->entityManager->persist($boostPurchase);
    }

    /**
     * Active (not-refunded) boost purchases the user holds in this competition.
     *
     * @return list<BoostPurchase>
     */
    public function findActiveByUserAndCompetition(Uuid $userId, Uuid $competitionId): array
    {
        /** @var list<BoostPurchase> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(BoostPurchase::class, 'b')
            ->where('b.user = :userId')
            ->andWhere('b.competition = :competitionId')
            ->andWhere('b.refundedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Batch variant of {@see findActiveByUserAndCompetition} — one query for many
     * competitions, feeding {@see \App\Service\Competition\CompetitionEntitlements::preload}
     * on the cross-competition read paths.
     *
     * @param list<Uuid> $competitionIds
     *
     * @return list<BoostPurchase>
     */
    public function findActiveByUserAndCompetitions(Uuid $userId, array $competitionIds): array
    {
        if (0 === count($competitionIds)) {
            return [];
        }

        /** @var list<BoostPurchase> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(BoostPurchase::class, 'b')
            ->where('b.user = :userId')
            ->andWhere('b.competition IN (:competitionIds)')
            ->andWhere('b.refundedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('competitionIds', $competitionIds)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findActiveByUserCompetitionType(Uuid $userId, Uuid $competitionId, BoostType $type): ?BoostPurchase
    {
        return $this->entityManager->createQueryBuilder()
            ->select('b')
            ->from(BoostPurchase::class, 'b')
            ->where('b.user = :userId')
            ->andWhere('b.competition = :competitionId')
            ->andWhere('b.type = :type')
            ->andWhere('b.refundedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('competitionId', $competitionId)
            ->setParameter('type', $type)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * All active boost purchases in a competition, oldest first — the refund set
     * when the manager re-enables premium.
     *
     * @return list<BoostPurchase>
     */
    public function listActiveByCompetition(Uuid $competitionId): array
    {
        /** @var list<BoostPurchase> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('b', 'u', 'c')
            ->from(BoostPurchase::class, 'b')
            ->innerJoin('b.user', 'u')
            ->innerJoin('b.competition', 'c')
            ->where('b.competition = :competitionId')
            ->andWhere('b.refundedAt IS NULL')
            ->setParameter('competitionId', $competitionId)
            ->orderBy('b.purchasedAt', 'ASC')
            ->addOrderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
