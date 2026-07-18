<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Competition;
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
    public function findForCompetition(Uuid $competitionId): array
    {
        /** @var list<LeaderboardTieResolution> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('r', 'u')
            ->from(LeaderboardTieResolution::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
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
    public function deleteForCompetitionAndUsers(Uuid $competitionId, array $userIds): void
    {
        if ([] === $userIds) {
            return;
        }

        /** @var list<LeaderboardTieResolution> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(LeaderboardTieResolution::class, 'r')
            ->where('r.competition = :competitionId')
            ->andWhere('r.user IN (:userIds)')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('userIds', $userIds)
            ->getQuery()
            ->getResult();

        foreach ($rows as $row) {
            $this->entityManager->remove($row);
        }
    }

    /**
     * Manual tie ranks describe a frozen final standing. When a source reopens
     * (more matches will be played), the resolutions of every competition
     * attached to it are stale and must go. DQL DELETE executes immediately.
     */
    public function deleteForMatchSource(Uuid $matchSourceId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(LeaderboardTieResolution::class, 'r')
            ->where(sprintf(
                'r.competition IN (SELECT c.id FROM %s c WHERE c.matchSource = :matchSourceId)',
                Competition::class,
            ))
            ->setParameter('matchSourceId', $matchSourceId)
            ->getQuery()
            ->execute();
    }
}
