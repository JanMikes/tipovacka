<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tournament;
use App\Exception\TournamentNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class TournamentRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Tournament $tournament): void
    {
        $this->entityManager->persist($tournament);
    }

    public function find(Uuid $id): ?Tournament
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t', 'o', 's')
            ->from(Tournament::class, 't')
            ->join('t.owner', 'o')
            ->join('t.sport', 's')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function get(Uuid $id): Tournament
    {
        return $this->find($id) ?? throw TournamentNotFound::withId($id);
    }

    /**
     * @return Tournament[]
     */
    public function findActivePublic(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Tournament::class, 't')
            ->where('t.visibility = :visibility')
            ->andWhere('t.finishedAt IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('visibility', \App\Enum\TournamentVisibility::Public)
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Tournament[]
     */
    public function findPrivateByOwner(Uuid $ownerId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Tournament::class, 't')
            ->where('t.owner = :ownerId')
            ->andWhere('t.visibility = :visibility')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('ownerId', $ownerId)
            ->setParameter('visibility', \App\Enum\TournamentVisibility::Private)
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Tournament[]
     */
    public function findAllActiveForAdmin(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(Tournament::class, 't')
            ->where('t.deletedAt IS NULL')
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
