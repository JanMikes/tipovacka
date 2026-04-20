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

    public function findActiveMembership(Uuid $userId, Uuid $groupId): ?Membership
    {
        return $this->entityManager->createQueryBuilder()
            ->select('m', 'g', 'u')
            ->from(Membership::class, 'm')
            ->innerJoin('m.group', 'g')
            ->innerJoin('m.user', 'u')
            ->where('m.user = :userId')
            ->andWhere('m.group = :groupId')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('groupId', $groupId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasActiveMembership(Uuid $userId, Uuid $groupId): bool
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(Membership::class, 'm')
            ->where('m.user = :userId')
            ->andWhere('m.group = :groupId')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('groupId', $groupId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $result;
    }

    public function hasActiveMembershipInTournament(Uuid $userId, Uuid $tournamentId): bool
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(Membership::class, 'm')
            ->innerJoin('m.group', 'g')
            ->where('m.user = :userId')
            ->andWhere('g.tournament = :tournamentId')
            ->andWhere('m.leftAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('tournamentId', $tournamentId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $result;
    }

    /**
     * @return list<Membership>
     */
    public function findActiveByGroup(Uuid $groupId): array
    {
        /** @var list<Membership> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('m', 'u')
            ->from(Membership::class, 'm')
            ->innerJoin('m.user', 'u')
            ->where('m.group = :groupId')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('groupId', $groupId)
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
            ->innerJoin('m.group', 'g')
            ->innerJoin('g.tournament', 't')
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
