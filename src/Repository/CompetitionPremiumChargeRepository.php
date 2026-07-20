<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompetitionPremiumCharge;
use App\Enum\CompetitionMonetization;
use App\Enum\PremiumChargeStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class CompetitionPremiumChargeRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CompetitionPremiumCharge $charge): void
    {
        $this->entityManager->persist($charge);
    }

    public function findByCompetitionAndMember(Uuid $competitionId, Uuid $memberId): ?CompetitionPremiumCharge
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c', 'comp', 'm')
            ->from(CompetitionPremiumCharge::class, 'c')
            ->innerJoin('c.competition', 'comp')
            ->innerJoin('c.member', 'm')
            ->where('c.competition = :competitionId')
            ->andWhere('c.member = :memberId')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('memberId', $memberId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countUncoveredForCompetition(Uuid $competitionId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(CompetitionPremiumCharge::class, 'c')
            ->where('c.competition = :competitionId')
            ->andWhere('c.status = :status')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('status', PremiumChargeStatus::Uncovered)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Every premium charge for a competition (any status), oldest first — the
     * admin premium-state list. Member is fetch-joined for display.
     *
     * @return list<CompetitionPremiumCharge>
     */
    public function findAllForCompetition(Uuid $competitionId): array
    {
        /** @var list<CompetitionPremiumCharge> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('c', 'm')
            ->from(CompetitionPremiumCharge::class, 'c')
            ->innerJoin('c.member', 'm')
            ->where('c.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->orderBy('c.createdAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return list<CompetitionPremiumCharge>
     */
    public function findChargedForCompetition(Uuid $competitionId): array
    {
        /** @var list<CompetitionPremiumCharge> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('c', 'm')
            ->from(CompetitionPremiumCharge::class, 'c')
            ->innerJoin('c.member', 'm')
            ->where('c.competition = :competitionId')
            ->andWhere('c.status = :status')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('status', PremiumChargeStatus::Charged)
            ->orderBy('c.createdAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Uncovered charges across a manager's still-premium competitions, oldest
     * first — the settle-on-top-up retry order.
     *
     * @return list<CompetitionPremiumCharge>
     */
    public function findUncoveredForOwner(Uuid $ownerId): array
    {
        /** @var list<CompetitionPremiumCharge> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('c', 'comp', 'm')
            ->from(CompetitionPremiumCharge::class, 'c')
            ->innerJoin('c.competition', 'comp')
            ->innerJoin('c.member', 'm')
            ->where('comp.owner = :ownerId')
            ->andWhere('comp.monetization = :premium')
            ->andWhere('comp.deletedAt IS NULL')
            ->andWhere('c.status = :uncovered')
            ->setParameter('ownerId', $ownerId)
            ->setParameter('premium', CompetitionMonetization::Premium)
            ->setParameter('uncovered', PremiumChargeStatus::Uncovered)
            ->orderBy('c.createdAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
