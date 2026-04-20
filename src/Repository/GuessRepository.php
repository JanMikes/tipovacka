<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Guess;
use App\Exception\GuessNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

// Non-final so PHPUnit can stub it in GuessVoterTest (voter-mocked repository exception).
class GuessRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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
            ->innerJoin('g.group', 'gr')
            ->where('g.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function get(Uuid $id): Guess
    {
        return $this->find($id) ?? throw GuessNotFound::withId($id);
    }

    public function findActiveByUserMatchGroup(Uuid $userId, Uuid $sportMatchId, Uuid $groupId): ?Guess
    {
        return $this->entityManager->createQueryBuilder()
            ->select('g', 'u', 'm', 'gr')
            ->from(Guess::class, 'g')
            ->innerJoin('g.user', 'u')
            ->innerJoin('g.sportMatch', 'm')
            ->innerJoin('g.group', 'gr')
            ->where('g.user = :userId')
            ->andWhere('g.sportMatch = :sportMatchId')
            ->andWhere('g.group = :groupId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('sportMatchId', $sportMatchId)
            ->setParameter('groupId', $groupId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Guess>
     */
    public function listActiveByGroupAndMatch(Uuid $groupId, Uuid $sportMatchId): array
    {
        /** @var list<Guess> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('g', 'u')
            ->from(Guess::class, 'g')
            ->innerJoin('g.user', 'u')
            ->where('g.group = :groupId')
            ->andWhere('g.sportMatch = :sportMatchId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('groupId', $groupId)
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
    public function listActiveByUserInTournament(Uuid $userId, Uuid $tournamentId, Uuid $groupId): array
    {
        /** @var list<Guess> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('g', 'm')
            ->from(Guess::class, 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->innerJoin('m.tournament', 't')
            ->where('g.user = :userId')
            ->andWhere('g.group = :groupId')
            ->andWhere('t.id = :tournamentId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('groupId', $groupId)
            ->setParameter('tournamentId', $tournamentId)
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
     * @return list<Guess>
     */
    public function findActiveForFinishedMatchesInTournament(Uuid $tournamentId): array
    {
        /** @var list<Guess> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('g', 'm')
            ->from(Guess::class, 'g')
            ->innerJoin('g.sportMatch', 'm')
            ->where('m.tournament = :tournamentId')
            ->andWhere('m.state = :finished')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('tournamentId', $tournamentId)
            ->setParameter('finished', \App\Enum\SportMatchState::Finished)
            ->orderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
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
