<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LeaderboardSnapshot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class LeaderboardSnapshotRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Replaces a competition's snapshot for a single day: the day's existing
     * rows are removed, then the supplied ones are persisted. Idempotent per
     * (competition, day) — re-running yields exactly the given standings.
     *
     * The delete is a bulk DQL statement executed immediately (against the DB,
     * not the UnitOfWork), so it commits BEFORE the pending INSERTs flush — the
     * unique (competition, user, day) index can never collide on a re-capture
     * (Doctrine would otherwise order INSERTs before DELETEs). Flush of the
     * inserts is left to the command bus's doctrine_transaction middleware.
     *
     * @param list<LeaderboardSnapshot> $snapshots
     */
    public function upsertDay(Uuid $competitionId, \DateTimeImmutable $day, array $snapshots): void
    {
        $this->entityManager->createQuery(
            'DELETE FROM App\Entity\LeaderboardSnapshot s WHERE s.competition = :competitionId AND s.day = :day',
        )
            ->setParameter('competitionId', $competitionId)
            ->setParameter('day', $day, Types::DATE_IMMUTABLE)
            ->execute();

        foreach ($snapshots as $snapshot) {
            $this->entityManager->persist($snapshot);
        }
    }

    /**
     * Standings from the latest snapshot day STRICTLY BEFORE `$day` (Prague) —
     * the baseline for today's Δ. Empty ⇒ no snapshot history yet (render a
     * neutral dot). Keyed by user RFC-4122.
     *
     * @return array<string, array{rank: int, points: int}>
     */
    public function latestBefore(Uuid $competitionId, \DateTimeImmutable $day): array
    {
        // Select the mapped `day` field (not MAX(day)): getArrayResult applies the
        // DATE_IMMUTABLE conversion for mapped fields, whereas an aggregate would
        // come back as a raw scalar string and mis-bind on the follow-up query.
        /** @var list<array{day: \DateTimeImmutable}> $latestDayRows */
        $latestDayRows = $this->entityManager->createQueryBuilder()
            ->select('s.day AS day')
            ->from(LeaderboardSnapshot::class, 's')
            ->where('s.competition = :competitionId')
            ->andWhere('s.day < :day')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('day', $day, Types::DATE_IMMUTABLE)
            ->orderBy('s.day', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();

        if ([] === $latestDayRows) {
            return [];
        }

        $latestDay = $latestDayRows[0]['day'];

        /** @var list<array{userId: string, rank: int, points: int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(s.user) AS userId', 's.rank AS rank', 's.points AS points')
            ->from(LeaderboardSnapshot::class, 's')
            ->where('s.competition = :competitionId')
            ->andWhere('s.day = :day')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('day', $latestDay, Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getArrayResult();

        $map = [];

        foreach ($rows as $row) {
            $map[$row['userId']] = ['rank' => (int) $row['rank'], 'points' => (int) $row['points']];
        }

        return $map;
    }

    /**
     * When the competition was last snapshotted (max createdAt), or null if
     * never — the daily sweep's "since" cursor for the evaluation-exists check.
     */
    public function latestSnapshotMomentFor(Uuid $competitionId): ?\DateTimeImmutable
    {
        // Mapped field (not MAX) so getArrayResult hydrates a real DateTimeImmutable.
        /** @var list<array{createdAt: \DateTimeImmutable}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('s.createdAt AS createdAt')
            ->from(LeaderboardSnapshot::class, 's')
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();

        return $rows[0]['createdAt'] ?? null;
    }

    /**
     * A member's snapshot history, oldest day first — the member breakdown's
     * „Vývoj" list.
     *
     * @return list<array{day: \DateTimeImmutable, rank: int, points: int}>
     */
    public function listDaysForUser(Uuid $competitionId, Uuid $userId): array
    {
        /** @var list<array{day: \DateTimeImmutable, rank: int, points: int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('s.day AS day', 's.rank AS rank', 's.points AS points')
            ->from(LeaderboardSnapshot::class, 's')
            ->where('s.competition = :competitionId')
            ->andWhere('s.user = :userId')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('userId', $userId)
            ->orderBy('s.day', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return $rows;
    }
}
