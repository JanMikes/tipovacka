<?php

declare(strict_types=1);

namespace App\Query\ListAdminTournaments;

use App\Entity\Group;
use App\Entity\Tournament;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListAdminTournamentsQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<AdminTournamentItem>
     */
    public function __invoke(ListAdminTournaments $query): array
    {
        /** @var Tournament[] $tournaments */
        $tournaments = $this->entityManager->createQueryBuilder()
            ->select('t', 'o', 's')
            ->from(Tournament::class, 't')
            ->innerJoin('t.owner', 'o')
            ->innerJoin('t.sport', 's')
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        $counts = $this->countActiveGroupsByTournament();

        return array_values(array_map(
            static fn (Tournament $t): AdminTournamentItem => new AdminTournamentItem(
                id: $t->id,
                name: $t->name,
                visibility: $t->visibility,
                sportCode: $t->sport->code,
                ownerNickname: $t->owner->nickname,
                isFinished: $t->isFinished,
                isDeleted: null !== $t->deletedAt,
                groupCount: $counts[$t->id->toRfc4122()] ?? 0,
            ),
            $tournaments,
        ));
    }

    /**
     * @return array<string, int>
     */
    private function countActiveGroupsByTournament(): array
    {
        /** @var list<array{tournamentId: mixed, c: mixed}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(g.tournament) AS tournamentId', 'COUNT(g.id) AS c')
            ->from(Group::class, 'g')
            ->where('g.deletedAt IS NULL')
            ->groupBy('g.tournament')
            ->getQuery()
            ->getScalarResult();

        $counts = [];

        foreach ($rows as $row) {
            $id = $row['tournamentId'];
            $key = $id instanceof Uuid ? $id->toRfc4122() : (string) $id;
            $counts[$key] = (int) $row['c'];
        }

        return $counts;
    }
}
