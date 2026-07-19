<?php

declare(strict_types=1);

namespace App\Query\ListDiscoverableGlobalCompetitions;

use App\Entity\Competition;
use App\Entity\Membership;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListDiscoverableGlobalCompetitionsQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<DiscoverableGlobalCompetitionItem>
     */
    public function __invoke(ListDiscoverableGlobalCompetitions $query): array
    {
        /** @var list<Competition> $competitions */
        $competitions = $this->entityManager->createQueryBuilder()
            ->select('g', 't', 's')
            ->from(Competition::class, 'g')
            ->innerJoin('g.matchSource', 't')
            ->innerJoin('t.sport', 's')
            ->where('g.isGlobal = true')
            ->andWhere('g.deletedAt IS NULL')
            ->andWhere('t.completedAt IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->orderBy('g.createdAt', 'DESC')
            ->addOrderBy('g.id', 'DESC')
            ->getQuery()
            ->getResult();

        $viewerMemberships = null !== $query->viewerId
            ? $this->activeMembershipCompetitionIds($query->viewerId)
            : [];

        $items = [];
        foreach ($competitions as $competition) {
            $playerCount = (int) $this->entityManager->createQueryBuilder()
                ->select('COUNT(m.id)')
                ->from(Membership::class, 'm')
                ->where('m.competition = :competitionId')
                ->andWhere('m.leftAt IS NULL')
                ->setParameter('competitionId', $competition->id)
                ->getQuery()
                ->getSingleScalarResult();

            $items[] = new DiscoverableGlobalCompetitionItem(
                competitionId: $competition->id,
                name: $competition->name,
                sportName: $competition->matchSource->sport->name,
                sourceStartAt: $competition->matchSource->startAt,
                sourceEndAt: $competition->matchSource->endAt,
                entryFeeCredits: $competition->entryFeeCredits,
                playerCount: $playerCount,
                viewerIsMember: isset($viewerMemberships[$competition->id->toRfc4122()]),
            );
        }

        return $items;
    }

    /**
     * @return array<string, true>
     */
    private function activeMembershipCompetitionIds(Uuid $viewerId): array
    {
        /** @var list<array{competitionId: mixed}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(m.competition) AS competitionId')
            ->from(Membership::class, 'm')
            ->where('m.user = :viewerId')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('viewerId', $viewerId)
            ->getQuery()
            ->getScalarResult();

        $ids = [];
        foreach ($rows as $row) {
            $id = $row['competitionId'];
            $key = $id instanceof Uuid ? $id->toRfc4122() : (string) $id;
            $ids[$key] = true;
        }

        return $ids;
    }
}
