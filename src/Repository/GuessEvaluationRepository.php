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

    public function countForTournament(Uuid $tournamentId): int
    {
        /** @var int $count */
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->where('m.tournament = :tournamentId')
            ->setParameter('tournamentId', $tournamentId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /**
     * @return list<GuessEvaluation>
     */
    public function listForTournament(Uuid $tournamentId): array
    {
        /** @var list<GuessEvaluation> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('e', 'rp', 'g')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->leftJoin('e.rulePoints', 'rp')
            ->where('m.tournament = :tournamentId')
            ->setParameter('tournamentId', $tournamentId)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function deleteAllForTournament(Uuid $tournamentId): void
    {
        /** @var list<GuessEvaluation> $evaluations */
        $evaluations = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin('e.guess', 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->where('m.tournament = :tournamentId')
            ->setParameter('tournamentId', $tournamentId)
            ->getQuery()
            ->getResult();

        foreach ($evaluations as $evaluation) {
            $this->entityManager->remove($evaluation);
        }
    }
}
