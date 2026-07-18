<?php

declare(strict_types=1);

namespace App\Query\ListMyAccessiblePrivateMatchSources;

use App\Entity\MatchSource;
use App\Entity\Membership;
use App\Enum\MatchSourceKind;
use App\Query\ListActivePublicMatchSources\MatchSourceListItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListMyAccessiblePrivateMatchSourcesQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<MatchSourceListItem>
     */
    public function __invoke(ListMyAccessiblePrivateMatchSources $query): array
    {
        $membershipSubquery = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(mg.matchSource)')
            ->from(Membership::class, 'um')
            ->innerJoin('um.competition', 'mg')
            ->where('um.user = :userId')
            ->andWhere('um.leftAt IS NULL')
            ->andWhere('mg.deletedAt IS NULL')
            ->getDQL();

        /** @var MatchSource[] $matchSources */
        $matchSources = $this->entityManager->createQueryBuilder()
            ->select('t', 'o')
            ->from(MatchSource::class, 't')
            ->innerJoin('t.owner', 'o')
            ->where('t.kind = :kind')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere(sprintf('(t.owner = :userId OR t.id IN (%s))', $membershipSubquery))
            ->setParameter('kind', MatchSourceKind::Private)
            ->setParameter('userId', $query->userId)
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
