<?php

declare(strict_types=1);

namespace App\Query\ListGlobalTeams;

use App\Entity\SportMatch;
use App\Entity\Team;
use App\Value\TeamView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListGlobalTeamsQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<TeamDirectoryItem>
     */
    public function __invoke(ListGlobalTeams $query): array
    {
        /** @var list<Team> $teams */
        $teams = $this->entityManager->createQueryBuilder()
            ->select('t', 's')
            ->from(Team::class, 't')
            ->innerJoin('t.sport', 's')
            ->where('t.matchSource IS NULL')
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();

        $counts = $this->matchCountsByTeam();

        return array_map(
            static fn (Team $team): TeamDirectoryItem => new TeamDirectoryItem(
                sportId: $team->sport->id,
                sportName: $team->sport->name,
                team: TeamView::fromTeam($team),
                matchCount: $counts[$team->id->toRfc4122()] ?? 0,
            ),
            $teams,
        );
    }

    /**
     * teamId (rfc4122) → number of non-deleted matches where it plays home or away.
     *
     * @return array<string, int>
     */
    private function matchCountsByTeam(): array
    {
        $counts = [];

        foreach (['homeTeam', 'awayTeam'] as $side) {
            /** @var list<array{tid: string, c: int}> $rows */
            $rows = $this->entityManager->createQueryBuilder()
                ->select(sprintf('IDENTITY(m.%s) AS tid', $side), 'COUNT(m.id) AS c')
                ->from(SportMatch::class, 'm')
                ->where('m.deletedAt IS NULL')
                ->groupBy('tid')
                ->getQuery()
                ->getResult();

            foreach ($rows as $row) {
                $counts[$row['tid']] = ($counts[$row['tid']] ?? 0) + (int) $row['c'];
            }
        }

        return $counts;
    }
}
