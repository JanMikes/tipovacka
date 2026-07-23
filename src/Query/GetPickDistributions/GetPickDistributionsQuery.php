<?php

declare(strict_types=1);

namespace App\Query\GetPickDistributions;

use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Query\GetMatchPickDistribution\MatchPickDistributionResult;
use App\Service\Competition\CompetitionMatchProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetPickDistributionsQuery
{
    public function __construct(
        private CompetitionMatchProvider $matchProvider,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetPickDistributions $query): PickDistributions
    {
        if (0 === count($query->competitionIds) || 0 === count($query->sportMatchIds)) {
            return new PickDistributions([]);
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select(
                'IDENTITY(g.competition) AS competitionId',
                'IDENTITY(g.sportMatch) AS sportMatchId',
                'SUM(CASE WHEN g.homeScore > g.awayScore THEN 1 ELSE 0 END) AS home',
                'SUM(CASE WHEN g.homeScore = g.awayScore THEN 1 ELSE 0 END) AS draw',
                'SUM(CASE WHEN g.homeScore < g.awayScore THEN 1 ELSE 0 END) AS away',
                'COUNT(g.id) AS total',
            )
            ->from(Guess::class, 'g')
            ->innerJoin(SportMatch::class, 'm', 'WITH', 'm.id = g.sportMatch')
            ->innerJoin(Competition::class, 'c', 'WITH', 'c.id = g.competition')
            ->where('g.competition IN (:competitionIds)')
            ->andWhere('g.sportMatch IN (:sportMatchIds)')
            ->andWhere('g.deletedAt IS NULL')
            ->andWhere('m.deletedAt IS NULL')
            ->groupBy('g.competition')
            ->addGroupBy('g.sportMatch')
            ->setParameter('competitionIds', $query->competitionIds)
            ->setParameter('sportMatchIds', $query->sportMatchIds);

        // Same membership semantics as the single-match query: a tip on a match
        // the competition no longer includes (subset dropped it, playoff excluded)
        // must not count towards its distribution.
        $this->matchProvider->applyRowLevelCompetitionMatchFilter($qb, 'm', 'c');

        /** @var list<array{competitionId: string, sportMatchId: string, home: int|string|null, draw: int|string|null, away: int|string|null, total: int|string}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        $byPair = [];

        foreach ($rows as $row) {
            $key = $row['competitionId'].':'.$row['sportMatchId'];
            $byPair[$key] = MatchPickDistributionResult::fromCounts(
                (int) ($row['home'] ?? 0),
                (int) ($row['draw'] ?? 0),
                (int) ($row['away'] ?? 0),
                (int) $row['total'],
            );
        }

        return new PickDistributions($byPair);
    }
}
