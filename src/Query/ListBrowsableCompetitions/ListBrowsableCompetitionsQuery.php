<?php

declare(strict_types=1);

namespace App\Query\ListBrowsableCompetitions;

use App\Entity\Competition;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Enum\CompetitionBrowseScope;
use App\Enum\CompetitionStateFilter;
use App\Enum\CompetitionVisibilityFilter;
use App\Enum\SportMatchState;
use App\Service\Competition\CompetitionMatchProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Four statements, whatever the list length:
 *   1. the scope's competitions (+ source, sport — one fetch join, hard-capped);
 *   2. active player counts, grouped;
 *   3. match progress (total / started / finished / live), grouped, respecting each
 *      competition's own match scope via {@see CompetitionMatchProvider::applyRowLevelCompetitionMatchFilter};
 *   4. the viewer's active memberships (skipped when anonymous).
 *
 * The predecessor (`ListDiscoverableGlobalCompetitions`) ran a COUNT per row
 * inside its loop and had no limit at all — item 07 replaced it with this.
 * Sport/visibility/state filtering then happens on the loaded (bounded) rows, so
 * adding a filter never adds a statement.
 */
#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListBrowsableCompetitionsQuery
{
    /**
     * Hard ceiling on how many competitions one scope may load before filtering.
     * Both scopes are naturally small (the competitions you organize; the global
     * competitions an admin curates), so this is a safety belt, not a paging
     * mechanism — the page itself pages the filtered result.
     */
    private const int SCOPE_LIMIT = 200;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompetitionMatchProvider $matchProvider,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ListBrowsableCompetitions $query): BrowsableCompetitionsResult
    {
        $competitions = $this->scopeCompetitions($query);

        if ([] === $competitions) {
            return new BrowsableCompetitionsResult(
                items: [],
                sportOptions: [],
                filteredCount: 0,
                totalCount: 0,
                page: 1,
                pageCount: 1,
            );
        }

        $competitionIds = array_map(static fn (Competition $c): Uuid => $c->id, $competitions);

        $playerCounts = $this->playerCounts($competitionIds);
        $matchAggregates = $this->matchAggregates($competitionIds);
        $viewerMemberships = null !== $query->viewerId
            ? $this->viewerMembershipIds($query->viewerId, $competitionIds)
            : [];

        $items = [];
        $sportOptions = [];

        foreach ($competitions as $competition) {
            $key = $competition->id->toRfc4122();
            $aggregate = $matchAggregates[$key] ?? ['total' => 0, 'started' => 0, 'finished' => 0, 'live' => 0];
            $sport = $competition->matchSource->sport;

            $sportOptions[$sport->id->toRfc4122()] ??= new SportFilterOption($sport->id, $sport->name);

            $items[] = new BrowsableCompetitionItem(
                competitionId: $competition->id,
                name: $competition->name,
                sportId: $sport->id,
                sportName: $sport->name,
                matchSourceName: $competition->matchSource->name,
                sourceStartAt: $competition->matchSource->startAt,
                sourceEndAt: $competition->matchSource->endAt,
                entryFeeCredits: $competition->entryFeeCredits,
                playerCount: $playerCounts[$key] ?? 0,
                matchCount: $aggregate['total'],
                startedMatchCount: $aggregate['started'],
                finishedMatchCount: $aggregate['finished'],
                liveMatchCount: $aggregate['live'],
                isGlobal: $competition->isGlobal,
                sourceIsCompleted: $competition->matchSource->isCompleted,
                viewerIsMember: isset($viewerMemberships[$key]),
                viewerIsOwner: null !== $query->viewerId && $competition->owner->id->equals($query->viewerId),
            );
        }

        $filtered = array_values(array_filter($items, static fn (BrowsableCompetitionItem $item): bool => self::matchesFilters($item, $query)));

        $pageSize = max(1, $query->pageSize);
        $pageCount = max(1, (int) ceil(count($filtered) / $pageSize));
        $page = min(max(1, $query->page), $pageCount);

        return new BrowsableCompetitionsResult(
            items: array_slice($filtered, ($page - 1) * $pageSize, $pageSize),
            sportOptions: array_values($sportOptions),
            filteredCount: count($filtered),
            totalCount: count($items),
            page: $page,
            pageCount: $pageCount,
        );
    }

    private static function matchesFilters(BrowsableCompetitionItem $item, ListBrowsableCompetitions $query): bool
    {
        if (null !== $query->sportId && !$item->sportId->equals($query->sportId)) {
            return false;
        }

        $visibilityMatches = match ($query->visibility) {
            CompetitionVisibilityFilter::All => true,
            CompetitionVisibilityFilter::Public => $item->isGlobal,
            CompetitionVisibilityFilter::Private => !$item->isGlobal,
        };

        if (!$visibilityMatches) {
            return false;
        }

        if (CompetitionStateFilter::All !== $query->state && $item->state !== $query->state) {
            return false;
        }

        $needle = null !== $query->search ? mb_strtolower(trim($query->search)) : '';

        if ('' === $needle) {
            return true;
        }

        return str_contains(mb_strtolower($item->name), $needle)
            || str_contains(mb_strtolower($item->matchSourceName), $needle);
    }

    /**
     * @return list<Competition>
     */
    private function scopeCompetitions(ListBrowsableCompetitions $query): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('c', 's', 'sp', 'o')
            ->from(Competition::class, 'c')
            ->innerJoin('c.matchSource', 's')
            ->innerJoin('s.sport', 'sp')
            ->innerJoin('c.owner', 'o')
            ->where('c.deletedAt IS NULL')
            ->andWhere('s.deletedAt IS NULL')
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults(self::SCOPE_LIMIT);

        if (CompetitionBrowseScope::Discoverable === $query->scope) {
            // Discovery advertises what can still be joined: a global competition
            // over a completed source is over, so it is not listed at all.
            $qb->andWhere('c.isGlobal = true')
                ->andWhere('s.completedAt IS NULL');
        } else {
            if (null === $query->viewerId) {
                return [];
            }

            $qb->andWhere('c.owner = :ownerId')
                ->setParameter('ownerId', $query->viewerId);
        }

        /** @var list<Competition> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * @param list<Uuid> $competitionIds
     *
     * @return array<string, int>
     */
    private function playerCounts(array $competitionIds): array
    {
        /** @var list<array{competitionId: string, players: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(m.competition) AS competitionId', 'COUNT(m.id) AS players')
            ->from(Membership::class, 'm')
            ->where('m.competition IN (:competitionIds)')
            ->andWhere('m.leftAt IS NULL')
            ->groupBy('m.competition')
            ->setParameter('competitionIds', $competitionIds)
            ->getQuery()
            ->getArrayResult();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row['competitionId']] = (int) $row['players'];
        }

        return $counts;
    }

    /**
     * @param list<Uuid> $competitionIds
     *
     * @return array<string, array{total: int, started: int, finished: int, live: int}>
     */
    private function matchAggregates(array $competitionIds): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $qb = $this->entityManager->createQueryBuilder()
            ->select(
                'c.id AS competitionId',
                'COUNT(m.id) AS total',
                'SUM(CASE WHEN m.kickoffAt <= :lbc_now THEN 1 ELSE 0 END) AS started',
                'SUM(CASE WHEN m.state = :lbc_finished THEN 1 ELSE 0 END) AS finished',
                'SUM(CASE WHEN m.state = :lbc_live THEN 1 ELSE 0 END) AS live',
            )
            ->from(Competition::class, 'c')
            ->innerJoin(SportMatch::class, 'm', 'WITH', 'm.matchSource = c.matchSource')
            ->where('c.id IN (:lbc_competitionIds)')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.state != :lbc_cancelled')
            ->groupBy('c.id')
            ->setParameter('lbc_competitionIds', $competitionIds)
            ->setParameter('lbc_now', $now)
            ->setParameter('lbc_finished', SportMatchState::Finished)
            ->setParameter('lbc_live', SportMatchState::Live)
            ->setParameter('lbc_cancelled', SportMatchState::Cancelled);

        $this->matchProvider->applyRowLevelCompetitionMatchFilter($qb, 'm', 'c');

        /** @var list<array{competitionId: mixed, total: int|string, started: int|string|null, finished: int|string|null, live: int|string|null}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        $aggregates = [];

        foreach ($rows as $row) {
            $id = $row['competitionId'];
            $key = $id instanceof Uuid ? $id->toRfc4122() : (string) $id;

            $aggregates[$key] = [
                'total' => (int) $row['total'],
                'started' => (int) ($row['started'] ?? 0),
                'finished' => (int) ($row['finished'] ?? 0),
                'live' => (int) ($row['live'] ?? 0),
            ];
        }

        return $aggregates;
    }

    /**
     * @param list<Uuid> $competitionIds
     *
     * @return array<string, true>
     */
    private function viewerMembershipIds(Uuid $viewerId, array $competitionIds): array
    {
        /** @var list<array{competitionId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(m.competition) AS competitionId')
            ->from(Membership::class, 'm')
            ->where('m.user = :viewerId')
            ->andWhere('m.competition IN (:competitionIds)')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('viewerId', $viewerId)
            ->setParameter('competitionIds', $competitionIds)
            ->getQuery()
            ->getArrayResult();

        $ids = [];

        foreach ($rows as $row) {
            $ids[$row['competitionId']] = true;
        }

        return $ids;
    }
}
