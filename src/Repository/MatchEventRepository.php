<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MatchEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class MatchEventRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(MatchEvent $matchEvent): void
    {
        $this->entityManager->persist($matchEvent);
    }

    /**
     * Timeline order: minute descending, events without a minute last.
     *
     * @return list<MatchEvent>
     */
    public function listByMatch(Uuid $sportMatchId): array
    {
        /** @var list<MatchEvent> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('e', 'p')
            ->addSelect('CASE WHEN e.minute IS NULL THEN 1 ELSE 0 END AS HIDDEN minute_is_null')
            ->from(MatchEvent::class, 'e')
            ->innerJoin('e.player', 'p')
            ->where('e.sportMatch = :sportMatchId')
            ->setParameter('sportMatchId', $sportMatchId)
            ->orderBy('minute_is_null', 'ASC')
            ->addOrderBy('e.minute', 'DESC')
            ->addOrderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countByMatch(Uuid $sportMatchId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(MatchEvent::class, 'e')
            ->where('e.sportMatch = :sportMatchId')
            ->setParameter('sportMatchId', $sportMatchId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Score-entry saves replace the whole event sheet (delete + insert).
     * DQL DELETE executes immediately; replacement rows insert on flush.
     */
    public function deleteByMatch(Uuid $sportMatchId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(MatchEvent::class, 'e')
            ->where('e.sportMatch = :sportMatchId')
            ->setParameter('sportMatchId', $sportMatchId)
            ->getQuery()
            ->execute();
    }
}
