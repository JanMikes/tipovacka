<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Competition;
use App\Enum\CompetitionMonetization;
use App\Exception\CompetitionNotFound;
use App\Exception\InvalidPin;
use App\Exception\InvalidShareableLink;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class CompetitionRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Competition $competition): void
    {
        $this->entityManager->persist($competition);
    }

    public function find(Uuid $id): ?Competition
    {
        return $this->entityManager->createQueryBuilder()
            ->select('g', 't', 'o')
            ->from(Competition::class, 'g')
            ->innerJoin('g.matchSource', 't')
            ->innerJoin('g.owner', 'o')
            ->where('g.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function get(Uuid $id): Competition
    {
        return $this->find($id) ?? throw CompetitionNotFound::withId($id);
    }

    public function getByShareableLinkToken(string $token): Competition
    {
        $competition = $this->entityManager->createQueryBuilder()
            ->select('g', 't', 'o')
            ->from(Competition::class, 'g')
            ->innerJoin('g.matchSource', 't')
            ->innerJoin('g.owner', 'o')
            ->where('g.shareableLinkToken = :token')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$competition instanceof Competition) {
            throw InvalidShareableLink::create();
        }

        return $competition;
    }

    public function getByPin(string $pin): Competition
    {
        $competition = $this->entityManager->createQueryBuilder()
            ->select('g', 't', 'o')
            ->from(Competition::class, 'g')
            ->innerJoin('g.matchSource', 't')
            ->innerJoin('g.owner', 'o')
            ->where('g.pin = :pin')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('pin', $pin)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$competition instanceof Competition) {
            throw InvalidPin::create();
        }

        return $competition;
    }

    public function pinExists(string $pin): bool
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(Competition::class, 'g')
            ->where('g.pin = :pin')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('pin', $pin)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $result;
    }

    /**
     * Premium competitions not yet reconciled — the reconcile sweep's candidate
     * set (the handler then keeps only those whose start moment has passed).
     *
     * @return list<Competition>
     */
    public function findPremiumAwaitingReconciliation(): array
    {
        /** @var list<Competition> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('g', 't', 'o')
            ->from(Competition::class, 'g')
            ->innerJoin('g.matchSource', 't')
            ->innerJoin('g.owner', 'o')
            ->where('g.monetization = :premium')
            ->andWhere('g.premiumReconciledAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('premium', CompetitionMonetization::Premium)
            ->orderBy('g.createdAt', 'ASC')
            ->addOrderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Every non-deleted competition — the reminder sweep's candidate set (the
     * handler then keeps only those with active members and open upcoming
     * matches). Eager-loads source + owner so downstream services avoid N+1.
     *
     * @return list<Competition>
     */
    public function findAllActive(): array
    {
        /** @var list<Competition> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('g', 't', 'o')
            ->from(Competition::class, 'g')
            ->innerJoin('g.matchSource', 't')
            ->innerJoin('g.owner', 'o')
            ->where('g.deletedAt IS NULL')
            ->orderBy('g.createdAt', 'ASC')
            ->addOrderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return Competition[]
     */
    public function findByMatchSource(Uuid $matchSourceId): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('g', 'o')
            ->from(Competition::class, 'g')
            ->innerJoin('g.owner', 'o')
            ->where('g.matchSource = :matchSourceId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('matchSourceId', $matchSourceId)
            ->orderBy('g.createdAt', 'DESC')
            ->addOrderBy('g.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
