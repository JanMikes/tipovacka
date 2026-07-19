<?php

declare(strict_types=1);

namespace App\Query\ListAdminCompetitions;

use App\Entity\Competition;
use App\Entity\Membership;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListAdminCompetitionsQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<AdminCompetitionItem>
     */
    public function __invoke(ListAdminCompetitions $query): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('g', 't', 'o')
            ->from(Competition::class, 'g')
            ->innerJoin('g.matchSource', 't')
            ->innerJoin('g.owner', 'o')
            ->orderBy('g.createdAt', 'DESC')
            ->addOrderBy('g.id', 'DESC');

        if (null !== $query->matchSourceId) {
            $qb->andWhere('g.matchSource = :matchSourceId')
                ->setParameter('matchSourceId', $query->matchSourceId);
        }

        /** @var Competition[] $competitions */
        $competitions = $qb->getQuery()->getResult();

        $memberCounts = $this->countActiveMembershipsByCompetition();

        return array_values(array_map(
            static fn (Competition $g): AdminCompetitionItem => new AdminCompetitionItem(
                id: $g->id,
                name: $g->name,
                matchSourceId: $g->matchSource->id,
                matchSourceName: $g->matchSource->name,
                ownerNickname: $g->owner->displayName,
                memberCount: $memberCounts[$g->id->toRfc4122()] ?? 0,
                isDeleted: null !== $g->deletedAt,
                isGlobal: $g->isGlobal,
                entryFeeCredits: $g->entryFeeCredits,
            ),
            $competitions,
        ));
    }

    /**
     * @return array<string, int>
     */
    private function countActiveMembershipsByCompetition(): array
    {
        /** @var list<array{competitionId: mixed, c: mixed}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(m.competition) AS competitionId', 'COUNT(m.id) AS c')
            ->from(Membership::class, 'm')
            ->where('m.leftAt IS NULL')
            ->groupBy('m.competition')
            ->getQuery()
            ->getScalarResult();

        $counts = [];

        foreach ($rows as $row) {
            $id = $row['competitionId'];
            $key = $id instanceof Uuid ? $id->toRfc4122() : (string) $id;
            $counts[$key] = (int) $row['c'];
        }

        return $counts;
    }
}
