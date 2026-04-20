<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Group;
use App\Exception\GroupNotFound;
use App\Exception\InvalidPin;
use App\Exception\InvalidShareableLink;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class GroupRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Group $group): void
    {
        $this->entityManager->persist($group);
    }

    public function find(Uuid $id): ?Group
    {
        return $this->entityManager->createQueryBuilder()
            ->select('g', 't', 'o')
            ->from(Group::class, 'g')
            ->innerJoin('g.tournament', 't')
            ->innerJoin('g.owner', 'o')
            ->where('g.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function get(Uuid $id): Group
    {
        return $this->find($id) ?? throw GroupNotFound::withId($id);
    }

    public function getByShareableLinkToken(string $token): Group
    {
        $group = $this->entityManager->createQueryBuilder()
            ->select('g', 't', 'o')
            ->from(Group::class, 'g')
            ->innerJoin('g.tournament', 't')
            ->innerJoin('g.owner', 'o')
            ->where('g.shareableLinkToken = :token')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$group instanceof Group) {
            throw InvalidShareableLink::create();
        }

        return $group;
    }

    public function getByPin(string $pin): Group
    {
        $group = $this->entityManager->createQueryBuilder()
            ->select('g', 't', 'o')
            ->from(Group::class, 'g')
            ->innerJoin('g.tournament', 't')
            ->innerJoin('g.owner', 'o')
            ->where('g.pin = :pin')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('pin', $pin)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$group instanceof Group) {
            throw InvalidPin::create();
        }

        return $group;
    }

    public function pinExists(string $pin): bool
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(Group::class, 'g')
            ->where('g.pin = :pin')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('pin', $pin)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $result;
    }

    /**
     * @return Group[]
     */
    public function findByTournament(Uuid $tournamentId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('g', 'o')
            ->from(Group::class, 'g')
            ->innerJoin('g.owner', 'o')
            ->where('g.tournament = :tournamentId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('tournamentId', $tournamentId)
            ->orderBy('g.createdAt', 'DESC')
            ->addOrderBy('g.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
