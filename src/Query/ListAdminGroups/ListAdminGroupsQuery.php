<?php

declare(strict_types=1);

namespace App\Query\ListAdminGroups;

use App\Entity\Group;
use App\Entity\Membership;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListAdminGroupsQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<AdminGroupItem>
     */
    public function __invoke(ListAdminGroups $query): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('g', 't', 'o')
            ->from(Group::class, 'g')
            ->innerJoin('g.tournament', 't')
            ->innerJoin('g.owner', 'o')
            ->orderBy('g.createdAt', 'DESC')
            ->addOrderBy('g.id', 'DESC');

        if (null !== $query->tournamentId) {
            $qb->andWhere('g.tournament = :tournamentId')
                ->setParameter('tournamentId', $query->tournamentId);
        }

        /** @var Group[] $groups */
        $groups = $qb->getQuery()->getResult();

        $memberCounts = $this->countActiveMembershipsByGroup();

        return array_values(array_map(
            static fn (Group $g): AdminGroupItem => new AdminGroupItem(
                id: $g->id,
                name: $g->name,
                tournamentId: $g->tournament->id,
                tournamentName: $g->tournament->name,
                ownerNickname: $g->owner->displayName,
                memberCount: $memberCounts[$g->id->toRfc4122()] ?? 0,
                isDeleted: null !== $g->deletedAt,
            ),
            $groups,
        ));
    }

    /**
     * @return array<string, int>
     */
    private function countActiveMembershipsByGroup(): array
    {
        /** @var list<array{groupId: mixed, c: mixed}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(m.group) AS groupId', 'COUNT(m.id) AS c')
            ->from(Membership::class, 'm')
            ->where('m.leftAt IS NULL')
            ->groupBy('m.group')
            ->getQuery()
            ->getScalarResult();

        $counts = [];

        foreach ($rows as $row) {
            $id = $row['groupId'];
            $key = $id instanceof Uuid ? $id->toRfc4122() : (string) $id;
            $counts[$key] = (int) $row['c'];
        }

        return $counts;
    }
}
