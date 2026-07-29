<?php

declare(strict_types=1);

namespace App\Query\GetMatchRanking;

use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Service\Competition\CompetitionMatchProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetMatchRankingQuery
{
    public function __construct(
        private CompetitionMatchProvider $matchProvider,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(GetMatchRanking $query): MatchRankingResult
    {
        // The guesses drive the board — NOT the evaluations. A match that has not
        // been scored yet has no evaluation at all, and its tips must still list
        // (the deadline opens the board long before any points exist).
        $guessesQb = $this->entityManager->createQueryBuilder()
            ->select('g', 'u')
            ->from(Guess::class, 'g')
            ->innerJoin('g.user', 'u')
            ->innerJoin('g.sportMatch', 'm')
            ->where('g.competition = :competitionId')
            ->andWhere('g.sportMatch = :matchId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('competitionId', $query->competitionId)
            ->setParameter('matchId', $query->sportMatchId);
        $this->matchProvider->applyCompetitionMatchFilter($guessesQb, 'm', $query->competitionId);

        /** @var list<Guess> $guesses */
        $guesses = $guessesQb->getQuery()->getResult();

        if ([] === $guesses) {
            return new MatchRankingResult(rows: [], isScored: false);
        }

        $pointsByGuess = $this->pointsByGuess($guesses);
        $baseRows = [];
        $isScored = false;

        foreach ($guesses as $guess) {
            $user = $guess->user;
            $hasNickname = null !== $user->nickname && '' !== $user->nickname;
            $hasFullName = '' !== $user->fullName;
            $points = $pointsByGuess[$guess->id->toRfc4122()] ?? null;
            $isScored = $isScored || null !== $points;

            $baseRows[] = [
                'userId' => $user->id,
                'nickname' => $user->displayName,
                'fullName' => ($hasNickname && $hasFullName) ? $user->fullName : null,
                'guessHome' => $guess->homeScore,
                'guessAway' => $guess->awayScore,
                'points' => $points,
            ];
        }

        usort(
            $baseRows,
            static fn (array $a, array $b): int => ($b['points'] ?? 0) <=> ($a['points'] ?? 0)
                ?: strcmp($a['nickname'], $b['nickname']),
        );

        $rows = [];

        foreach ($baseRows as $row) {
            $points = $row['points'];
            $rank = null;

            if (null !== $points) {
                $rank = 1;

                foreach ($baseRows as $other) {
                    if (null !== $other['points'] && $other['points'] > $points) {
                        ++$rank;
                    }
                }
            }

            $rows[] = new MatchRankingRow(
                rank: $rank,
                userId: $row['userId'],
                nickname: $row['nickname'],
                fullName: $row['fullName'],
                guessHome: $row['guessHome'],
                guessAway: $row['guessAway'],
                totalPoints: $points,
            );
        }

        return new MatchRankingResult(rows: $rows, isScored: $isScored);
    }

    /**
     * One extra query for the whole board (never one per row) — `Guess` has no
     * inverse side to the evaluation, so the points are looked up by guess id.
     *
     * @param list<Guess> $guesses
     *
     * @return array<string, int> guess id RFC4122 → points scored
     */
    private function pointsByGuess(array $guesses): array
    {
        /** @var list<array{guessId: string, totalPoints: int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(e.guess) AS guessId', 'e.totalPoints AS totalPoints')
            ->from(GuessEvaluation::class, 'e')
            ->where('e.guess IN (:guesses)')
            ->setParameter('guesses', array_map(static fn (Guess $guess): string => $guess->id->toRfc4122(), $guesses))
            ->getQuery()
            ->getArrayResult();

        $byGuess = [];

        foreach ($rows as $row) {
            $byGuess[$row['guessId']] = (int) $row['totalPoints'];
        }

        return $byGuess;
    }
}
