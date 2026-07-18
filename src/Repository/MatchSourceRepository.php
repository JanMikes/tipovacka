<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MatchSource;
use App\Exception\MatchSourceNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class MatchSourceRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(MatchSource $matchSource): void
    {
        $this->entityManager->persist($matchSource);
    }

    public function find(Uuid $id): ?MatchSource
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t', 'o', 's')
            ->from(MatchSource::class, 't')
            ->join('t.owner', 'o')
            ->join('t.sport', 's')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function get(Uuid $id): MatchSource
    {
        return $this->find($id) ?? throw MatchSourceNotFound::withId($id);
    }

    /**
     * @return MatchSource[]
     */
    public function findActiveCurated(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(MatchSource::class, 't')
            ->where('t.kind = :kind')
            ->andWhere('t.finishedAt IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('kind', \App\Enum\MatchSourceKind::Curated)
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MatchSource[]
     */
    public function findPrivateByOwner(Uuid $ownerId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(MatchSource::class, 't')
            ->where('t.owner = :ownerId')
            ->andWhere('t.kind = :kind')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('ownerId', $ownerId)
            ->setParameter('kind', \App\Enum\MatchSourceKind::Private)
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return MatchSource[]
     */
    public function findAllActiveForAdmin(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(MatchSource::class, 't')
            ->where('t.deletedAt IS NULL')
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
