<?php

declare(strict_types=1);

namespace App\Query\ListAdminMatchSources;

use App\Entity\Competition;
use App\Entity\MatchSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListAdminMatchSourcesQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<AdminMatchSourceItem>
     */
    public function __invoke(ListAdminMatchSources $query): array
    {
        /** @var MatchSource[] $matchSources */
        $matchSources = $this->entityManager->createQueryBuilder()
            ->select('t', 'o', 's')
            ->from(MatchSource::class, 't')
            ->innerJoin('t.owner', 'o')
            ->innerJoin('t.sport', 's')
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        $counts = $this->countActiveCompetitionsByMatchSource();

        return array_values(array_map(
            static fn (MatchSource $t): AdminMatchSourceItem => new AdminMatchSourceItem(
                id: $t->id,
                name: $t->name,
                kind: $t->kind,
                sportCode: $t->sport->code,
                ownerNickname: $t->owner->displayName,
                isFinished: $t->isFinished,
                isDeleted: null !== $t->deletedAt,
                competitionCount: $counts[$t->id->toRfc4122()] ?? 0,
            ),
            $matchSources,
        ));
    }

    /**
     * @return array<string, int>
     */
    private function countActiveCompetitionsByMatchSource(): array
    {
        /** @var list<array{matchSourceId: mixed, c: mixed}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(g.matchSource) AS matchSourceId', 'COUNT(g.id) AS c')
            ->from(Competition::class, 'g')
            ->where('g.deletedAt IS NULL')
            ->groupBy('g.matchSource')
            ->getQuery()
            ->getScalarResult();

        $counts = [];

        foreach ($rows as $row) {
            $id = $row['matchSourceId'];
            $key = $id instanceof Uuid ? $id->toRfc4122() : (string) $id;
            $counts[$key] = (int) $row['c'];
        }

        return $counts;
    }
}
