<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GuessScorer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class GuessScorerRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * How many scorer tips exist for a match (across all competitions). Used by
     * the team-rename lock: players are keyed by team name, so a rename with
     * standing scorer tips would silently detach them from the roster pool.
     */
    public function countByMatch(Uuid $sportMatchId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(gs.id)')
            ->from(GuessScorer::class, 'gs')
            ->innerJoin('gs.guess', 'g')
            ->where('g.sportMatch = :sportMatchId')
            ->setParameter('sportMatchId', $sportMatchId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Hard-deletes the scorer tips of a voided guess (called by GuessVoidedHandler,
     * same cleanup style as GuessEvaluationRepository::deleteByGuess). DQL DELETE
     * executes immediately.
     */
    public function deleteByGuess(Uuid $guessId): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(GuessScorer::class, 'gs')
            ->where('gs.guess = :guessId')
            ->setParameter('guessId', $guessId)
            ->getQuery()
            ->execute();
    }

    /**
     * Scorer-tip player names per guess, for list views (one query for many guesses).
     *
     * @param list<Uuid> $guessIds
     *
     * @return array<string, list<string>> guess UUID → sorted player names
     */
    public function playerNamesByGuessIds(array $guessIds): array
    {
        if ([] === $guessIds) {
            return [];
        }

        /** @var list<array{guessId: Uuid|string, playerName: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(gs.guess) AS guessId', 'p.name AS playerName')
            ->from(GuessScorer::class, 'gs')
            ->innerJoin('gs.player', 'p')
            ->where('gs.guess IN (:guessIds)')
            ->setParameter('guessIds', $guessIds)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();

        $names = [];

        foreach ($rows as $row) {
            $key = $row['guessId'] instanceof Uuid ? $row['guessId']->toRfc4122() : (string) $row['guessId'];
            $names[$key][] = $row['playerName'];
        }

        return $names;
    }
}
