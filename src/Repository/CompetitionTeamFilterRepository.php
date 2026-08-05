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
     * The competition's filter teams grouped by the scope layer that owns them.
     * One global directory team may filter two different zdroje of the same
     * soutěž, so the grouping — not a flat team set — is what membership tests
     * against.
     *
     * @return array<string, array<string, true>> layer UUID → set of team UUIDs
     */
    public function teamIdsByLayer(Uuid $competitionId): array
    {
        /** @var list<array{layerId: string, teamId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(f.competitionSource) AS layerId', 'IDENTITY(f.team) AS teamId')
            ->from(CompetitionTeamFilter::class, 'f')
            ->where('f.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getArrayResult();

        $byLayer = [];

        foreach ($rows as $row) {
            $byLayer[(string) $row['layerId']][(string) $row['teamId']] = true;
        }

        return $byLayer;
    }

    /**
     * One layer's filter teams as display DTOs, alphabetised — the manage
     * screen edits a single layer at a time.
     *
     * @return list<TeamView>
     */
    public function teamViewsForLayer(Uuid $competitionSourceId): array
    {
        /** @var list<Team> $teams */
        $teams = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Team::class, 't')
            ->innerJoin(CompetitionTeamFilter::class, 'f', 'WITH', 'f.team = t')
            ->where('f.competitionSource = :competitionSourceId')
            ->setParameter('competitionSourceId', $competitionSourceId)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (Team $team): TeamView => TeamView::fromTeam($team), $teams);
    }

    /**
     * The filter teams as display DTOs, alphabetised — for the competition
     * detail summary and the manage-filter page.
     *
     * @return list<TeamView>
     */
    public function teamViewsFor(Uuid $competitionId): array
    {
        // Team is the ROOT alias: DQL cannot hydrate a joined entity when the
        // root entity is not selected too ("Cannot select entity through
        // identification variables without choosing at least one root entity
        // alias"), so the filter row is joined onto the team, not the reverse.
        /** @var list<Team> $teams */
        $teams = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Team::class, 't')
            ->innerJoin(CompetitionTeamFilter::class, 'f', 'WITH', 'f.team = t')
            ->where('f.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (Team $team): TeamView => TeamView::fromTeam($team), $teams);
    }
}
