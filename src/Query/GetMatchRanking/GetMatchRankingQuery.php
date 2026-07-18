<?php

declare(strict_types=1);

namespace App\Query\GetMatchRanking;

use App\Entity\GuessEvaluation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetMatchRankingQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetMatchRanking $query): MatchRankingResult
    {
        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $this->entityManager->createQueryBuilder()
            ->select('e', 'g', 'u')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->innerJoin('g.user', 'u')
            ->where('g.competition = :competitionId')
            ->andWhere('g.sportMatch = :matchId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('competitionId', $query->competitionId)
            ->setParameter('matchId', $query->sportMatchId)
            ->getQuery()
            ->getResult();

        $baseRows = [];

        foreach ($evaluations as $evaluation) {
            $guess = $evaluation->guess;
            $user = $guess->user;
            $hasNickname = null !== $user->nickname && '' !== $user->nickname;
            $hasFullName = '' !== $user->fullName;

            $baseRows[] = [
                'userId' => $user->id,
                'nickname' => $user->displayName,
                'fullName' => ($hasNickname && $hasFullName) ? $user->fullName : null,
                'guessHome' => $guess->homeScore,
                'guessAway' => $guess->awayScore,
                'points' => $evaluation->totalPoints,
            ];
        }

        usort(
            $baseRows,
            static fn (array $a, array $b): int => $b['points'] <=> $a['points']
                ?: strcmp($a['nickname'], $b['nickname']),
        );

        $rows = [];

        foreach ($baseRows as $row) {
            $rank = 1;

            foreach ($baseRows as $other) {
                if ($other['points'] > $row['points']) {
                    ++$rank;
                }
            }

            $rows[] = new MatchRankingRow(
                rank: $rank,
                userId: $row['userId'],
                nickname: $row['nickname'],
                fullName: $row['fullName'],
                guessHome: $row['guessHome'],
                guessAway: $row['guessAway'],
                totalPoints: $row['points'],
            );
        }

        return new MatchRankingResult(rows: $rows);
    }
}
