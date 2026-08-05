<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionsPageStats;

use App\Entity\Competition;
use App\Entity\CompetitionSource;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\PragueCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * ⚠ Nothing in the app dispatches this query since item 24 — read the message's
 * docblock (`GetCompetitionsPageStats`) before assuming it is dead code.
 *
 * Four statements, all aggregates over the WHOLE platform — the hero costs the
 * same for every visitor because it says the same thing to every visitor.
 *
 * The scope is every live competition over a live zdroj zápasů. Only counts leave
 * this query: no name, no owner, no id — so a private competition contributes to
 * the totals without becoming visible anywhere.
 */
#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCompetitionsPageStatsQuery
{
    private const string NEW_PLAYER_WINDOW = '-7 days';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompetitionMatchProvider $matchProvider,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetCompetitionsPageStats $query): CompetitionsPageStatsResult
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        [$playerCount, $newPlayerCount] = $this->playerCounts($now);
        $matchStats = $this->matchStats($now);

        return new CompetitionsPageStatsResult(
            activeCompetitionCount: $this->activeCompetitionCount(),
            liveCompetitionCount: $matchStats['liveCompetitions'],
            todayMatchCount: $matchStats['today'],
            playerCount: $playerCount,
            newPlayerCount: $newPlayerCount,
            matchCount: $matchStats['total'],
            matchSourceCount: count($matchStats['matchSources']),
        );
    }

    /**
     * Competitions still running („aktivní soutěže") — at least one of the
     * zdroje they draw from has not been marked completed. A multi-source
     * soutěž is over only once every layer is, mirroring
     * {@see Competition::$scheduleIsComplete}.
     */
    private function activeCompetitionCount(): int
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Competition::class, 'c')
            ->innerJoin('c.matchSource', 's')
            ->andWhere(sprintf(
                'EXISTS(SELECT 1 FROM %s cps_cs INNER JOIN cps_cs.matchSource cps_ms WHERE cps_cs.competition = c AND cps_ms.completedAt IS NULL AND cps_ms.deletedAt IS NULL)',
                CompetitionSource::class,
            ));
        $this->applyLiveScope($qb);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function playerCounts(\DateTimeImmutable $now): array
    {
        $total = (int) $this->membershipQb()
            ->select('COUNT(DISTINCT m.user)')
            ->getQuery()
            ->getSingleScalarResult();

        $fresh = (int) $this->membershipQb()
            ->select('COUNT(DISTINCT m.user)')
            ->andWhere('m.joinedAt >= :since')
            ->setParameter('since', $now->modify(self::NEW_PLAYER_WINDOW))
            ->getQuery()
            ->getSingleScalarResult();

        return [$total, $fresh];
    }

    private function membershipQb(): QueryBuilder
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->from(Membership::class, 'm')
            ->innerJoin('m.competition', 'c')
            ->innerJoin('c.matchSource', 's')
            ->andWhere('m.leftAt IS NULL');
        $this->applyLiveScope($qb);

        return $qb;
    }

    /**
     * Distinct matches across every competition (a match shared by two
     * competitions is one watched match), plus today's, the live-competition
     * count and the zdroje zápasů those matches are spread over.
     *
     * @return array{total: int, today: int, liveCompetitions: int, matchSources: array<string, true>}
     */
    private function matchStats(\DateTimeImmutable $now): array
    {
        $dayStart = PragueCalendar::day($now);
        $dayEnd = $dayStart->modify('+1 day');

        $qb = $this->entityManager->createQueryBuilder()
            ->select(
                'c.id AS competitionId',
                'm.id AS matchId',
                'IDENTITY(m.matchSource) AS matchSourceId',
                'm.kickoffAt AS kickoffAt',
                'm.state AS state',
            )
            ->from(Competition::class, 'c')
            ->innerJoin('c.matchSource', 's')
            // Candidates = every match of every zdroj the competition draws
            // from; the row-level scope filter below then keeps only the ones
            // its layers actually accept. Joining on `c.matchSource` instead
            // would pin the query to the headline zdroj and lose every other
            // layer's matches.
            ->innerJoin(CompetitionSource::class, 'cs', 'WITH', 'cs.competition = c')
            ->innerJoin(SportMatch::class, 'm', 'WITH', 'm.matchSource = cs.matchSource')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.state != :cps_cancelled')
            ->setParameter('cps_cancelled', SportMatchState::Cancelled);
        $this->applyLiveScope($qb);

        $this->matchProvider->applyRowLevelCompetitionMatchFilter($qb, 'm', 'c');

        /** @var list<array{competitionId: mixed, matchId: mixed, matchSourceId: mixed, kickoffAt: \DateTimeImmutable, state: SportMatchState}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        $matchIds = [];
        $todayIds = [];
        $liveCompetitions = [];
        $matchSources = [];

        foreach ($rows as $row) {
            $matchKey = self::key($row['matchId']);
            $matchIds[$matchKey] = true;
            $matchSources[self::key($row['matchSourceId'])] = true;

            $kickoff = $row['kickoffAt']->setTimezone(PragueCalendar::timezone());

            if ($kickoff >= $dayStart && $kickoff < $dayEnd) {
                $todayIds[$matchKey] = true;
            }

            if (SportMatchState::Live === $row['state']) {
                $liveCompetitions[self::key($row['competitionId'])] = true;
            }
        }

        return [
            'total' => count($matchIds),
            'today' => count($todayIds),
            'liveCompetitions' => count($liveCompetitions),
            'matchSources' => $matchSources,
        ];
    }

    /**
     * Aliases `c` (Competition) and `s` (MatchSource) must already be joined.
     */
    private function applyLiveScope(QueryBuilder $qb): void
    {
        $qb->andWhere('c.deletedAt IS NULL')
            ->andWhere('s.deletedAt IS NULL');
    }

    private static function key(mixed $id): string
    {
        return $id instanceof Uuid ? $id->toRfc4122() : (string) $id;
    }
}
