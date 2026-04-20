<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Exception\SportMatchNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class SportMatchRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function save(SportMatch $sportMatch): void
    {
        $this->entityManager->persist($sportMatch);
    }

    public function find(Uuid $id): ?SportMatch
    {
        return $this->entityManager->createQueryBuilder()
            ->select('m', 't', 'o', 's')
            ->from(SportMatch::class, 'm')
            ->innerJoin('m.tournament', 't')
            ->innerJoin('t.owner', 'o')
            ->innerJoin('t.sport', 's')
            ->where('m.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function get(Uuid $id): SportMatch
    {
        return $this->find($id) ?? throw SportMatchNotFound::withId($id);
    }

    /**
     * @return list<SportMatch>
     */
    public function listByTournament(
        Uuid $tournamentId,
        ?SportMatchState $state = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->where('m.tournament = :tournamentId')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('tournamentId', $tournamentId)
            ->orderBy('m.kickoffAt', 'ASC')
            ->addOrderBy('m.id', 'ASC');

        if (null !== $state) {
            $qb->andWhere('m.state = :state')
                ->setParameter('state', $state);
        }

        if (null !== $from) {
            $qb->andWhere('m.kickoffAt >= :from')
                ->setParameter('from', $from);
        }

        if (null !== $to) {
            $qb->andWhere('m.kickoffAt <= :to')
                ->setParameter('to', $to);
        }

        /** @var list<SportMatch> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * @return list<SportMatch>
     */
    public function listUpcomingForUser(Uuid $userId, \DateTimeImmutable $now): array
    {
        $membershipSubquery = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(Membership::class, 'ms')
            ->innerJoin('ms.group', 'g')
            ->where('ms.user = :userId')
            ->andWhere('g.tournament = t.id')
            ->andWhere('ms.leftAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->getDQL();

        /** @var list<SportMatch> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('m', 't')
            ->from(SportMatch::class, 'm')
            ->innerJoin('m.tournament', 't')
            ->where('m.state = :state')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.kickoffAt >= :now')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('EXISTS('.$membershipSubquery.')')
            ->setParameter('state', SportMatchState::Scheduled)
            ->setParameter('now', $now)
            ->setParameter('userId', $userId)
            ->orderBy('m.kickoffAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
