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
            ->innerJoin('m.matchSource', 't')
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
    public function listByMatchSource(
        Uuid $matchSourceId,
        ?SportMatchState $state = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): array {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(SportMatch::class, 'm')
            ->where('m.matchSource = :matchSourceId')
            ->andWhere('m.deletedAt IS NULL')
            ->setParameter('matchSourceId', $matchSourceId)
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
     * All non-deleted, non-cancelled matches across the match_sources of the soutěže
     * (competitions) the user is an active member of — any state (Scheduled / Live /
     * Finished / Postponed). Powers the cross-soutěž "Zápasy" page. Ordered with
     * still-upcoming matches first (soonest kickoff), then past matches (most
     * recent first), so the next match to tip surfaces at the top.
     *
     * @return list<SportMatch>
     */
    public function listAllForUser(Uuid $userId, \DateTimeImmutable $now): array
    {
        $membershipSubquery = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(Membership::class, 'ms')
            ->innerJoin('ms.competition', 'g')
            ->where('ms.user = :userId')
            ->andWhere('g.matchSource = t.id')
            ->andWhere('ms.leftAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->getDQL();

        /** @var list<SportMatch> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('m', 't')
            ->from(SportMatch::class, 'm')
            ->innerJoin('m.matchSource', 't')
            ->where('m.state != :cancelled')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('EXISTS('.$membershipSubquery.')')
            ->setParameter('cancelled', SportMatchState::Cancelled)
            ->setParameter('userId', $userId)
            ->orderBy('m.kickoffAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        // Split into upcoming (soonest first) and past (most recent first) so the
        // next match to tip surfaces at the top while results read newest-first.
        $upcoming = [];
        $past = [];
        foreach ($result as $match) {
            if ($match->kickoffAt >= $now) {
                $upcoming[] = $match;
            } else {
                $past[] = $match;
            }
        }

        return array_merge($upcoming, array_reverse($past));
    }

    /**
     * @return list<SportMatch>
     */
    public function listUpcomingForUser(Uuid $userId, \DateTimeImmutable $now): array
    {
        $membershipSubquery = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(Membership::class, 'ms')
            ->innerJoin('ms.competition', 'g')
            ->where('ms.user = :userId')
            ->andWhere('g.matchSource = t.id')
            ->andWhere('ms.leftAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->getDQL();

        /** @var list<SportMatch> $result */
        $result = $this->entityManager->createQueryBuilder()
            ->select('m', 't')
            ->from(SportMatch::class, 'm')
            ->innerJoin('m.matchSource', 't')
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
