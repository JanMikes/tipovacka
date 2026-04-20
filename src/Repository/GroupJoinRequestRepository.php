<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GroupJoinRequest;
use App\Exception\GroupJoinRequestNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class GroupJoinRequestRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(GroupJoinRequest $request): void
    {
        $this->entityManager->persist($request);
    }

    public function find(Uuid $id): ?GroupJoinRequest
    {
        return $this->entityManager->createQueryBuilder()
            ->select('r', 'g', 'u')
            ->from(GroupJoinRequest::class, 'r')
            ->innerJoin('r.group', 'g')
            ->innerJoin('r.user', 'u')
            ->where('r.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function get(Uuid $id): GroupJoinRequest
    {
        return $this->find($id) ?? throw GroupJoinRequestNotFound::withId($id);
    }

    /**
     * @return list<GroupJoinRequest>
     */
    public function findPendingByGroup(Uuid $groupId): array
    {
        /** @var list<GroupJoinRequest> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('r', 'u')
            ->from(GroupJoinRequest::class, 'r')
            ->innerJoin('r.user', 'u')
            ->where('r.group = :groupId')
            ->andWhere('r.decidedAt IS NULL')
            ->setParameter('groupId', $groupId)
            ->orderBy('r.requestedAt', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return list<GroupJoinRequest>
     */
    public function findPendingByUser(Uuid $userId): array
    {
        /** @var list<GroupJoinRequest> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('r', 'g', 't')
            ->from(GroupJoinRequest::class, 'r')
            ->innerJoin('r.group', 'g')
            ->innerJoin('g.tournament', 't')
            ->where('r.user = :userId')
            ->andWhere('r.decidedAt IS NULL')
            ->setParameter('userId', $userId)
            ->orderBy('r.requestedAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function hasPendingFor(Uuid $userId, Uuid $groupId): bool
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(GroupJoinRequest::class, 'r')
            ->where('r.user = :userId')
            ->andWhere('r.group = :groupId')
            ->andWhere('r.decidedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('groupId', $groupId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $result;
    }
}
