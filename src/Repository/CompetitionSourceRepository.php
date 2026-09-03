<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompetitionSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class CompetitionSourceRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CompetitionSource $source): void
    {
        $this->entityManager->persist($source);
    }

    public function remove(CompetitionSource $source): void
    {
        $this->entityManager->remove($source);
    }

    public function get(Uuid $id): CompetitionSource
    {
        $source = $this->entityManager->find(CompetitionSource::class, $id);

        if (null === $source) {
            throw new \RuntimeException(sprintf('Vrstva zdroje zápasů "%s" neexistuje.', $id->toRfc4122()));
        }

        return $source;
    }

    /**
     * The competition's scope layers in display order.
     *
     * @return list<CompetitionSource>
     */
    public function listByCompetition(Uuid $competitionId): array
    {
        /** @var list<CompetitionSource> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('cs')
            ->from(CompetitionSource::class, 'cs')
            ->where('cs.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->orderBy('cs.position', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findByCompetitionAndSource(Uuid $competitionId, Uuid $matchSourceId): ?CompetitionSource
    {
        /** @var ?CompetitionSource $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('cs')
            ->from(CompetitionSource::class, 'cs')
            ->where('cs.competition = :competitionId')
            ->andWhere('cs.matchSource = :matchSourceId')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('matchSourceId', $matchSourceId)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /**
     * How many zdroje each competition draws from, and whether every one of
     * them is completed — ONE statement for a whole list, so a card can print
     * „a 2 další" and „dohráno" without reaching through the lazy layer
     * collection per row and putting the cost back on the list length.
     * The batch form of {@see \App\Entity\Competition::$sourcesLabel} and
     * {@see \App\Entity\Competition::$scheduleIsComplete}.
     *
     * @param list<Uuid> $competitionIds
     *
     * @return array<string, array{layerCount: int, scheduleComplete: bool}>
     */
    public function scopeSummaries(array $competitionIds): array
    {
        if ([] === $competitionIds) {
            return [];
        }

        /** @var list<array{competitionId: string, total: int|string, completed: int|string|null}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                'IDENTITY(cs.competition) AS competitionId',
                'COUNT(cs.id) AS total',
                'SUM(CASE WHEN ms.completedAt IS NOT NULL THEN 1 ELSE 0 END) AS completed',
            )
            ->from(CompetitionSource::class, 'cs')
            ->innerJoin('cs.matchSource', 'ms')
            ->where('cs.competition IN (:competitionIds)')
            ->groupBy('cs.competition')
            ->setParameter('competitionIds', $competitionIds)
            ->getQuery()
            ->getArrayResult();

        $map = [];

        foreach ($rows as $row) {
            $total = (int) $row['total'];
            $map[(string) $row['competitionId']] = [
                'layerCount' => $total,
                'scheduleComplete' => $total > 0 && (int) $row['completed'] === $total,
            ];
        }

        return $map;
    }

    /**
     * How many zdroje the competition draws from, and how many of them play
     * extra time — ONE statement, so a rule surface can say whether
     * `overtime_exact` could ever score without hydrating the layer collection.
     * Fed to {@see \App\Enum\OvertimeCoverage::fromCounts()}.
     *
     * @return array{total: int, withOvertime: int}
     */
    public function overtimeSourceCounts(Uuid $competitionId): array
    {
        /** @var array{total: int|string, withOvertime: int|string|null} $row */
        $row = $this->entityManager->createQueryBuilder()
            ->select(
                'COUNT(cs.id) AS total',
                'SUM(CASE WHEN ms.hasOvertime = true THEN 1 ELSE 0 END) AS withOvertime',
            )
            ->from(CompetitionSource::class, 'cs')
            ->innerJoin('cs.matchSource', 'ms')
            ->where('cs.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $row['total'],
            'withOvertime' => (int) $row['withOvertime'],
        ];
    }

    /** The next free position in the competition's layer order. */
    public function nextPosition(Uuid $competitionId): int
    {
        $max = $this->entityManager->createQueryBuilder()
            ->select('MAX(cs.position)')
            ->from(CompetitionSource::class, 'cs')
            ->where('cs.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getSingleScalarResult();

        return null === $max ? 0 : (int) $max + 1;
    }
}
