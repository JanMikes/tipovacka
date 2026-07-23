<?php

declare(strict_types=1);

namespace App\Query\GetMatchPickDistribution;

use App\Entity\Guess;
use App\Service\Competition\CompetitionMatchProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetMatchPickDistributionQuery
{
    public function __construct(
        private CompetitionMatchProvider $matchProvider,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetMatchPickDistribution $query): MatchPickDistributionResult
    {
        $rowQb = $this->entityManager->createQueryBuilder()
            ->select(
                'SUM(CASE WHEN g.homeScore > g.awayScore THEN 1 ELSE 0 END) AS home',
                'SUM(CASE WHEN g.homeScore = g.awayScore THEN 1 ELSE 0 END) AS draw',
                'SUM(CASE WHEN g.homeScore < g.awayScore THEN 1 ELSE 0 END) AS away',
                'COUNT(g.id) AS total',
            )
            ->from(Guess::class, 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->where('g.competition = :competitionId')
            ->andWhere('g.sportMatch = :matchId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('competitionId', $query->competitionId)
            ->setParameter('matchId', $query->sportMatchId);
        $this->matchProvider->applyCompetitionMatchFilter($rowQb, 'm', $query->competitionId);

        /** @var array{home: int|string|null, draw: int|string|null, away: int|string|null, total: int|string} $row */
        $row = $rowQb->getQuery()->getSingleResult();

        $home = (int) ($row['home'] ?? 0);
        $draw = (int) ($row['draw'] ?? 0);
        $away = (int) ($row['away'] ?? 0);
        $total = (int) $row['total'];

        return MatchPickDistributionResult::fromCounts($home, $draw, $away, $total);
    }
}
