<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompetitionTeamFilter;
use App\Entity\Team;
use App\Value\TeamView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class CompetitionTeamFilterRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CompetitionTeamFilter $filter): void
    {
        $this->entityManager->persist($filter);
    }

    public function remove(CompetitionTeamFilter $filter): void
    {
        $this->entityManager->remove($filter);
    }

    /**
     * @return list<CompetitionTeamFilter>
     */
    public function listByCompetition(Uuid $competitionId): array
    {
        /** @var list<CompetitionTeamFilter> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(CompetitionTeamFilter::class, 'f')
            ->where('f.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return list<string> filter team UUIDs (RFC 4122)
     */
    public function teamIdsFor(Uuid $competitionId): array
    {
        /** @var list<array{teamId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(f.team) AS teamId')
            ->from(CompetitionTeamFilter::class, 'f')
            ->where('f.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): string => (string) $row['teamId'], $rows);
    }

    /**
     * The filter teams as display DTOs, alphabetised — for the competition
     * detail summary and the manage-filter page.
     *
     * @return list<TeamView>
     */
    public function teamViewsFor(Uuid $competitionId): array
    {
        /** @var list<Team> $teams */
        $teams = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(CompetitionTeamFilter::class, 'f')
            ->join('f.team', 't')
            ->where('f.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (Team $team): TeamView => TeamView::fromTeam($team), $teams);
    }
}
