<?php

declare(strict_types=1);

namespace App\Query\ListDiscoverablePublicMatchSources;

use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\Membership;
use App\Enum\MatchSourceVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListDiscoverablePublicMatchSourcesQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<DiscoverableMatchSourceItem>
     */
    public function __invoke(ListDiscoverablePublicMatchSources $query): array
    {
        // Sub-query: IDs of match_sources where user already has an active membership
        // (via any active competition in that match source).
        $membershipSubquery = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(mg.matchSource)')
            ->from(Membership::class, 'um')
            ->innerJoin('um.competition', 'mg')
            ->where('um.user = :userId')
            ->andWhere('um.leftAt IS NULL')
            ->andWhere('mg.deletedAt IS NULL')
            ->getDQL();

        /** @var list<MatchSource> $matchSources */
        $matchSources = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(MatchSource::class, 't')
            ->where('t.visibility = :visibility')
            ->andWhere('t.finishedAt IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->andWhere('t.owner != :userId')
            ->andWhere(sprintf('t.id NOT IN (%s)', $membershipSubquery))
            ->setParameter('visibility', MatchSourceVisibility::Public)
            ->setParameter('userId', $query->userId)
            ->orderBy('t.createdAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($matchSources as $matchSource) {
            $competitionCount = (int) $this->entityManager->createQueryBuilder()
                ->select('COUNT(g.id)')
                ->from(Competition::class, 'g')
                ->where('g.matchSource = :matchSourceId')
                ->andWhere('g.deletedAt IS NULL')
                ->setParameter('matchSourceId', $matchSource->id)
                ->getQuery()
                ->getSingleScalarResult();

            $memberCount = (int) $this->entityManager->createQueryBuilder()
                ->select('COUNT(m.id)')
                ->from(Membership::class, 'm')
                ->innerJoin('m.competition', 'g')
                ->where('g.matchSource = :matchSourceId')
                ->andWhere('g.deletedAt IS NULL')
                ->andWhere('m.leftAt IS NULL')
                ->setParameter('matchSourceId', $matchSource->id)
                ->getQuery()
                ->getSingleScalarResult();

            $items[] = new DiscoverableMatchSourceItem(
                matchSourceId: $matchSource->id,
                name: $matchSource->name,
                startAt: $matchSource->startAt,
                endAt: $matchSource->endAt,
                competitionCount: $competitionCount,
                memberCount: $memberCount,
            );
        }

        return $items;
    }
}
