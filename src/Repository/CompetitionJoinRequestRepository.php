<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CompetitionJoinRequest;
use App\Exception\CompetitionJoinRequestNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class CompetitionJoinRequestRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(CompetitionJoinRequest $request): void
    {
        $this->entityManager->persist($request);
    }

    public function find(Uuid $id): ?CompetitionJoinRequest
    {
        return $this->entityManager->createQueryBuilder()
            ->select('r', 'g', 'u')
            ->from(CompetitionJoinRequest::class, 'r')
            ->innerJoin('r.competition', 'g')
            ->innerJoin('r.user', 'u')
            ->where('r.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function get(Uuid $id): CompetitionJoinRequest
    {
        return $this->find($id) ?? throw CompetitionJoinRequestNotFound::withId($id);
    }

    /**
     * @return list<CompetitionJoinRequest>
     */
    public function findPendingByCompetition(Uuid $competitionId): array
    {
        /** @var list<CompetitionJoinRequest> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('r', 'u')
            ->from(CompetitionJoinRequest::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.competition = :competitionId')
            ->andWhere('r.decidedAt IS NULL')
            ->setParameter('competitionId', $competitionId)
            ->orderBy('r.requestedAt', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return list<CompetitionJoinRequest>
     */
    public function findPendingByUser(Uuid $userId): array
    {
        /** @var list<CompetitionJoinRequest> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('r', 'g', 't')
            ->from(CompetitionJoinRequest::class, 'r')
            ->innerJoin('r.competition', 'g')
            ->innerJoin('g.matchSource', 't')
            ->where('r.user = :userId')
            ->andWhere('r.decidedAt IS NULL')
            ->setParameter('userId', $userId)
            ->orderBy('r.requestedAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function hasPendingFor(Uuid $userId, Uuid $competitionId): bool
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(CompetitionJoinRequest::class, 'r')
            ->where('r.user = :userId')
            ->andWhere('r.competition = :competitionId')
            ->andWhere('r.decidedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('competitionId', $competitionId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $result;
    }
}
