<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompetitionMatchSelection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class CompetitionMatchSelectionRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CompetitionMatchSelection $selection): void
    {
        $this->entityManager->persist($selection);
    }

    public function remove(CompetitionMatchSelection $selection): void
    {
        $this->entityManager->remove($selection);
    }

    /**
     * @return list<CompetitionMatchSelection>
     */
    public function listByCompetition(Uuid $competitionId): array
    {
        /** @var list<CompetitionMatchSelection> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(CompetitionMatchSelection::class, 's')
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return list<string> selected sport match UUIDs (RFC 4122)
     */
    public function selectedMatchIds(Uuid $competitionId): array
    {
        /** @var list<array{sportMatchId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(s.sportMatch) AS sportMatchId')
            ->from(CompetitionMatchSelection::class, 's')
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => (string) $row['sportMatchId'], $rows);
    }

    /**
     * The competition's selections grouped by the scope layer that owns them —
     * one query for the whole competition, so {@see \App\Service\Competition\CompetitionMatchProvider}
     * can answer membership per layer without an N+1 over layers.
     *
     * @return array<string, array<string, true>> layer UUID → set of selected match UUIDs
     */
    public function selectedMatchIdsByLayer(Uuid $competitionId): array
    {
        /** @var list<array{layerId: string, sportMatchId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(s.competitionSource) AS layerId', 'IDENTITY(s.sportMatch) AS sportMatchId')
            ->from(CompetitionMatchSelection::class, 's')
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getArrayResult();

        $byLayer = [];

        foreach ($rows as $row) {
            $byLayer[(string) $row['layerId']][(string) $row['sportMatchId']] = true;
        }

        return $byLayer;
    }
}
