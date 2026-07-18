<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GuessEvaluation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class GuessEvaluationRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(GuessEvaluation $evaluation): void
    {
        $this->entityManager->persist($evaluation);
    }

    public function find(Uuid $id): ?GuessEvaluation
    {
        return $this->entityManager->createQueryBuilder()
            ->select('e', 'rp')
            ->from(GuessEvaluation::class, 'e')
            ->leftJoin('e.rulePoints', 'rp')
            ->where('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByGuess(Uuid $guessId): ?GuessEvaluation
    {
        return $this->entityManager->createQueryBuilder()
            ->select('e', 'rp')
            ->from(GuessEvaluation::class, 'e')
            ->leftJoin('e.rulePoints', 'rp')
            ->where('e.guess = :guessId')
            ->setParameter('guessId', $guessId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteByGuess(Uuid $guessId): void
    {
        $evaluation = $this->findByGuess($guessId);

        if (null === $evaluation) {
            return;
        }

        $this->entityManager->remove($evaluation);
    }

    public function deleteByMatch(Uuid $sportMatchId): void
    {
        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->where('g.sportMatch = :sportMatchId')
            ->setParameter('sportMatchId', $sportMatchId)
            ->getQuery()
            ->getResult();

        foreach ($evaluations as $evaluation) {
            $this->entityManager->remove($evaluation);
        }
    }

    public function countForCompetition(Uuid $competitionId): int
    {
        /** @var int $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->where('g.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /**
     * Deletes every evaluation belonging to the competition's guesses. A guess is
     * competition-scoped, so no match filtering is needed (and stale evaluations of
     * matches later removed from a subset selection get cleaned up too).
     */
    public function deleteAllForCompetition(Uuid $competitionId): void
    {
        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->where('g.competition = :competitionId')
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getResult();

        foreach ($evaluations as $evaluation) {
            $this->entityManager->remove($evaluation);
        }
    }
}
