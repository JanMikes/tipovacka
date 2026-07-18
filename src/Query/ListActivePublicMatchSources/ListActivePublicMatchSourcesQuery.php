<?php

declare(strict_types=1);

namespace App\Query\ListActivePublicMatchSources;

use App\Entity\MatchSource;
use App\Enum\MatchSourceKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListActivePublicMatchSourcesQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<MatchSourceListItem>
     */
    public function __invoke(ListActivePublicMatchSources $query): array
    {
        /** @var MatchSource[] $matchSources */
        $matchSources = $this->entityManager->createQueryBuilder()
            ->select('t', 'o')
            ->from(MatchSource::class, 't')
            ->innerJoin('t.owner', 'o')
            ->where('t.kind = :kind')
            ->andWhere('t.completedAt IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('kind', MatchSourceKind::Curated)
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        return array_values(array_map(
            static fn (MatchSource $t): MatchSourceListItem => new MatchSourceListItem(
                id: $t->id,
                name: $t->name,
                kind: $t->kind,
                ownerNickname: $t->owner->displayName,
                createdAt: $t->createdAt,
                startAt: $t->startAt,
                endAt: $t->endAt,
                completedAt: $t->completedAt,
            ),
            $matchSources,
        ));
    }
}
