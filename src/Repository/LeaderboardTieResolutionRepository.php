<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LeaderboardTieResolution;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class LeaderboardTieResolutionRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(LeaderboardTieResolution $resolution): void
    {
        $this->entityManager->persist($resolution);
    }

    /**
     * @return array<string, LeaderboardTieResolution>
     */
    public function findForGroup(Uuid $groupId): array
    {
        /** @var list<LeaderboardTieResolution> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('r', 'u')
            ->from(LeaderboardTieResolution::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.group = :groupId')
            ->setParameter('groupId', $groupId)
            ->getQuery()
            ->getResult();

        $map = [];

        foreach ($rows as $row) {
            $map[$row->user->id->toRfc4122()] = $row;
        }

        return $map;
    }

    /**
     * @param list<Uuid> $userIds
     */
    public function deleteForGroupAndUsers(Uuid $groupId, array $userIds): void
    {
        if ([] === $userIds) {
            return;
        }

        /** @var list<LeaderboardTieResolution> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(LeaderboardTieResolution::class, 'r')
            ->where('r.group = :groupId')
            ->andWhere('r.user IN (:userIds)')
            ->setParameter('groupId', $groupId)
            ->setParameter('userIds', $userIds)
            ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            $this->entityManager->remove($row);
        }
    }
}
