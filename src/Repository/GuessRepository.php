<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Guess;
use App\Exception\GuessNotFound;
use App\Service\Competition\CompetitionMatchProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

// Non-final so PHPUnit can stub it in GuessVoterTest (voter-mocked repository exception).
class GuessRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompetitionMatchProvider $matchProvider,
    ) {
    }

    public function save(Guess $guess): void
    {
        $this->entityManager->persist($guess);
    }

    public function find(Uuid $id): ?Guess
    {
        return $this->entityManager->createQueryBuilder()
            ->select('g', 'u', 'm', 'gr')
            ->from(Guess::class, 'g')
            ->innerJoin('g.user', 'u')
            ->innerJoin('g.sportMatch', 'm')
            ->innerJoin('g.competition', 'gr')
            ->where('g.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function get(Uuid $id): Guess
    {
        return $this->find($id) ?? throw GuessNotFound::withId($id);
    }

    public function findActiveByUserMatchCompetition(Uuid $userId, Uuid $sportMatchId, Uuid $competitionId): ?Guess
    {
        return $this->entityManager->createQueryBuilder()
            ->select('g', 'u', 'm', 'gr')
            ->from(Guess::class, 'g')
            ->innerJoin('g.user', 'u')
            ->innerJoin('g.sportMatch', 'm')
            ->innerJoin('g.competition', 'gr')
            ->where('g.user = :userId')
            ->andWhere('g.sportMatch = :sportMatchId')
            ->andWhere('g.competition = :competitionId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('sportMatchId', $sportMatchId)
            ->setParameter('competitionId', $competitionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Whether ANY active (non-voided) tip exists for the match in the
     * competition — used to keep a re-selected match from becoming late-added
     * (and thereby reopening already-revealed tips).
     */
    public function hasActiveInCompetitionAndMatch(Uuid $competitionId, Uuid $sportMatchId): bool
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(Guess::class, 'g')
            ->where('g.competition = :competitionId')
            ->andWhere('g.sportMatch = :sportMatchId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('sportMatchId', $sportMatchId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $result;
    }

    /**
     * @return list<Guess>
     */
    public function listActiveByCompetitionAndMatch(Uuid $competitionId, Uuid $sportMatchId): array
    {
        // Scorer tips fetch-joined — list views render them per guess (no N+1).
        /** @var list<Guess> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('g', 'u', 'gs', 'gsp')
            ->from(Guess::class, 'g')
            ->innerJoin('g.user', 'u')
            ->leftJoin('g.scorers', 'gs')
            ->leftJoin('gs.player', 'gsp')
            ->where('g.competition = :competitionId')
            ->andWhere('g.sportMatch = :sportMatchId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('sportMatchId', $sportMatchId)
            ->orderBy('g.submittedAt', 'ASC')
            ->addOrderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return list<Guess>
     */
    public function listActiveByUserInMatchSource(Uuid $userId, Uuid $matchSourceId, Uuid $competitionId): array
    {
        /** @var list<Guess> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('g', 'm')
            ->from(Guess::class, 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->innerJoin('m.matchSource', 't')
            ->where('g.user = :userId')
            ->andWhere('g.competition = :competitionId')
            ->andWhere('t.id = :matchSourceId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('competitionId', $competitionId)
            ->setParameter('matchSourceId', $matchSourceId)
            ->orderBy('m.kickoffAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return list<Guess>
     */
    public function findActiveByMatch(Uuid $sportMatchId): array
    {
        /** @var list<Guess> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('g')
            ->from(Guess::class, 'g')
            ->where('g.sportMatch = :sportMatchId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('sportMatchId', $sportMatchId)
            ->orderBy('g.submittedAt', 'ASC')
            ->addOrderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Active guesses of the competition whose match is finished AND currently
     * belongs to the competition ({@see CompetitionMatchProvider} — subset
     * selections may have changed since the guess was submitted).
     *
     * @return list<Guess>
     */
    public function findActiveForFinishedMatchesInCompetition(Uuid $competitionId): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('g', 'm')
            ->from(Guess::class, 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->where('g.competition = :competitionId')
            ->andWhere('m.state = :finished')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('finished', \App\Enum\SportMatchState::Finished)
            ->orderBy('g.id', 'ASC');

        $this->matchProvider->applyCompetitionMatchFilter($qb, 'm', $competitionId);

        /** @var list<Guess> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Cheap COUNT variant of {@see findActiveForFinishedMatchesInCompetition} —
     * "would a recalculation of this competition produce any evaluations?".
     */
    public function countActiveForFinishedMatchesInCompetition(Uuid $competitionId): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(g.id)')
            ->from(Guess::class, 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->where('g.competition = :competitionId')
            ->andWhere('m.state = :finished')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('competitionId', $competitionId)
            ->setParameter('finished', \App\Enum\SportMatchState::Finished);

        $this->matchProvider->applyCompetitionMatchFilter($qb, 'm', $competitionId);

        /** @var int $count */
        $count = $qb->getQuery()->getSingleScalarResult();

        return $count;
    }

    /**
     * Bulk-void all active guesses for a given match.
     * Returns the number of affected entities.
     */
    public function voidAllForMatch(Uuid $sportMatchId, \DateTimeImmutable $now): int
    {
        /** @var list<Guess> $guesses */
        $guesses = $this->entityManager->createQueryBuilder()
            ->select('g')
            ->from(Guess::class, 'g')
            ->where('g.sportMatch = :sportMatchId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('sportMatchId', $sportMatchId)
            ->getQuery()
            ->getResult();

        foreach ($guesses as $guess) {
            $guess->voidGuess($now);
        }

        return count($guesses);
    }
}
