<?php

declare(strict_types=1);

namespace App\Query\ListMyOwnedMatchSources;

use App\Entity\MatchSource;
use App\Query\ListActivePublicMatchSources\MatchSourceListItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListMyOwnedMatchSourcesQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<MatchSourceListItem>
     */
    public function __invoke(ListMyOwnedMatchSources $query): array
    {
        /** @var MatchSource[] $matchSources */
        $matchSources = $this->entityManager->createQueryBuilder()
            ->select('t', 'o')
            ->from(MatchSource::class, 't')
            ->innerJoin('t.owner', 'o')
            ->where('t.owner = :ownerId')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('ownerId', $query->ownerId)
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        return array_values(array_map(
            static fn (MatchSource $t): MatchSourceListItem => new MatchSourceListItem(
                id: $t->id,
                name: $t->name,
                visibility: $t->visibility,
                ownerNickname: $t->owner->displayName,
                createdAt: $t->createdAt,
                startAt: $t->startAt,
                endAt: $t->endAt,
                finishedAt: $t->finishedAt,
            ),
            $matchSources,
        ));
    }
}
