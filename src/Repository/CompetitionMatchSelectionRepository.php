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
}
