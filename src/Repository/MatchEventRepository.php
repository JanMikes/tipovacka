<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MatchEvent;
use App\Enum\MatchEventType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

// Non-final so GuessEvaluatorTest can fake it (counting stub proving events load once per match).
class MatchEventRepository
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
     * Distinct players with at least one goal event in the match — the
     * scorer_hit rule's source of truth (via {@see \App\Service\Scoring\MatchContext}).
     *
     * @return list<Uuid>
     */
    public function goalScorerPlayerIds(Uuid $sportMatchId): array
    {
        /** @var list<array{playerId: Uuid|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT IDENTITY(e.player) AS playerId')
            ->from(MatchEvent::class, 'e')
            ->where('e.sportMatch = :sportMatchId')
            ->andWhere('e.type = :goal')
            ->setParameter('sportMatchId', $sportMatchId)
            ->setParameter('goal', MatchEventType::Goal)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): Uuid => $row['playerId'] instanceof Uuid ? $row['playerId'] : Uuid::fromString((string) $row['playerId']),
            $rows,
        );
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
