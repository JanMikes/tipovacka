<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Membership;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class MembershipRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Membership $membership): void
    {
        $this->entityManager->persist($membership);
    }

    public function findActiveMembership(Uuid $userId, Uuid $competitionId): ?Membership
    {
        return $this->entityManager->createQueryBuilder()
            ->select('m', 'g', 'u')
            ->from(Membership::class, 'm')
            ->innerJoin('m.competition', 'g')
            ->innerJoin('m.user', 'u')
            ->where('m.user = :userId')
            ->andWhere('m.competition = :competitionId')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Active member count (leftAt IS NULL) for a competition.
     */
    public function countActiveMembers(Uuid $competitionId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Membership::class, 'm')
            ->where('m.competition = :competitionId')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count of ALL memberships ever created for a competition — including those
     * left (leftAt set). Drives the global fee-lock: once anyone besides the owner
     * has EVER joined (count > 1), the fee/monetization lock permanently, even if
     * that joiner later leaves (their row persists). A count based on active
     * members would wrongly UNLOCK when the last non-owner leaves.
     */
    public function countAllForCompetition(Uuid $competitionId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Membership::class, 'm')
            ->where('m.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function hasActiveMembership(Uuid $userId, Uuid $competitionId): bool
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.competition = :competitionId')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('competitionId', $competitionId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $result;
    }

    public function hasActiveMembershipInMatchSource(Uuid $userId, Uuid $matchSourceId): bool
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(Membership::class, 'm')
            ->innerJoin('m.competition', 'g')
            ->where('m.user = :userId')
            ->andWhere('g.matchSource = :matchSourceId')
            ->andWhere('m.leftAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('matchSourceId', $matchSourceId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $result;
    }

    /**
     * @return list<Membership>
     */
    public function findActiveByCompetition(Uuid $competitionId): array
    {
        /** @var list<Membership> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('m', 'u')
            ->from(Membership::class, 'm')
            ->innerJoin('m.user', 'u')
            ->where('m.competition = :competitionId')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('competitionId', $competitionId)
            ->orderBy('m.joinedAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return list<Membership>
     */
    public function findMyActive(Uuid $userId): array
    {
        /** @var list<Membership> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('m', 'g', 't', 'o')
            ->from(Membership::class, 'm')
            ->innerJoin('m.competition', 'g')
            ->innerJoin('g.matchSource', 't')
            ->innerJoin('g.owner', 'o')
            ->where('m.user = :userId')
            ->andWhere('m.leftAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->orderBy('m.joinedAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
