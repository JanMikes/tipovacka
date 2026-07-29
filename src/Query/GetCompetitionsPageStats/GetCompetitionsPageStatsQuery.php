<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionsPageStats;

use App\Entity\Competition;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\PragueCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Five statements, all aggregates — the hero must never cost more the more
 * competitions a visitor is in.
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

        $scope = $this->scope($query->viewerId);

        if ([] === $scope) {
            return new CompetitionsPageStatsResult(0, 0, 0, 0, 0, 0, 0);
        }

        $competitionIds = array_map(static fn (array $row): Uuid => $row['id'], $scope);
        $matchSourceIds = [];
        $activeCompetitionCount = 0;

        foreach ($scope as $row) {
            $matchSourceIds[$row['matchSourceId']->toRfc4122()] = true;

            if (!$row['sourceCompleted']) {
                ++$activeCompetitionCount;
            }
        }

        [$playerCount, $newPlayerCount] = $this->playerCounts($competitionIds, $now);
        $matchStats = $this->matchStats($competitionIds, $now);

        return new CompetitionsPageStatsResult(
            activeCompetitionCount: $activeCompetitionCount,
            liveCompetitionCount: $matchStats['liveCompetitions'],
            todayMatchCount: $matchStats['today'],
            playerCount: $playerCount,
            newPlayerCount: $newPlayerCount,
            matchCount: $matchStats['total'],
            matchSourceCount: count($matchSourceIds),
        );
    }

    /**
     * The competitions the hero speaks for: a signed-in visitor's own world
     * (member of OR organizer of), an anonymous visitor's public list.
     *
     * @return list<array{id: Uuid, matchSourceId: Uuid, sourceCompleted: bool}>
     */
    private function scope(?Uuid $viewerId): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('c.id AS id', 'IDENTITY(c.matchSource) AS matchSourceId', 's.completedAt AS completedAt')
            ->from(Competition::class, 'c')
            ->innerJoin('c.matchSource', 's')
            ->where('c.deletedAt IS NULL')
            ->andWhere('s.deletedAt IS NULL');

        if (null === $viewerId) {
            $qb->andWhere('c.isGlobal = true')
                ->andWhere('s.completedAt IS NULL');
        } else {
            $qb->andWhere(sprintf(
                'c.owner = :viewerId OR EXISTS(SELECT 1 FROM %s cps_m WHERE cps_m.competition = c AND cps_m.user = :viewerId AND cps_m.leftAt IS NULL)',
                Membership::class,
            ))->setParameter('viewerId', $viewerId);
        }

        /** @var list<array{id: Uuid, matchSourceId: mixed, completedAt: \DateTimeImmutable|null}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        return array_map(
            static function (array $row): array {
                $sourceId = $row['matchSourceId'];

                return [
                    'id' => $row['id'],
                    'matchSourceId' => $sourceId instanceof Uuid ? $sourceId : Uuid::fromString((string) $sourceId),
                    'sourceCompleted' => null !== $row['completedAt'],
                ];
            },
            $rows,
        );
    }

    /**
     * @param list<Uuid> $competitionIds
     *
     * @return array{0: int, 1: int}
     */
    private function playerCounts(array $competitionIds, \DateTimeImmutable $now): array
    {
        $total = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT m.user)')
            ->from(Membership::class, 'm')
            ->where('m.competition IN (:competitionIds)')
            ->andWhere('m.leftAt IS NULL')
            ->setParameter('competitionIds', $competitionIds)
            ->getQuery()
            ->getSingleScalarResult();

        $fresh = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT m.user)')
            ->from(Membership::class, 'm')
            ->where('m.competition IN (:competitionIds)')
            ->andWhere('m.leftAt IS NULL')
            ->andWhere('m.joinedAt >= :since')
            ->setParameter('competitionIds', $competitionIds)
            ->setParameter('since', $now->modify(self::NEW_PLAYER_WINDOW))
            ->getQuery()
            ->getSingleScalarResult();

        return [$total, $fresh];
    }

    /**
     * Distinct matches across the scope (a match shared by two competitions is
     * one watched match), plus today's and the live-competition counts.
     *
     * @param list<Uuid> $competitionIds
     *
     * @return array{total: int, today: int, liveCompetitions: int}
     */
    private function matchStats(array $competitionIds, \DateTimeImmutable $now): array
    {
        $dayStart = PragueCalendar::day($now);
        $dayEnd = $dayStart->modify('+1 day');

        $qb = $this->entityManager->createQueryBuilder()
            ->select('c.id AS competitionId', 'm.id AS matchId', 'm.kickoffAt AS kickoffAt', 'm.state AS state')
            ->from(Competition::class, 'c')
            ->innerJoin(SportMatch::class, 'm', 'WITH', 'm.matchSource = c.matchSource')
            ->where('c.id IN (:cps_competitionIds)')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.state != :cps_cancelled')
            ->setParameter('cps_competitionIds', $competitionIds)
            ->setParameter('cps_cancelled', SportMatchState::Cancelled);

        $this->matchProvider->applyRowLevelCompetitionMatchFilter($qb, 'm', 'c');

        /** @var list<array{competitionId: mixed, matchId: mixed, kickoffAt: \DateTimeImmutable, state: SportMatchState}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        $matchIds = [];
        $todayIds = [];
        $liveCompetitions = [];

        foreach ($rows as $row) {
            $matchId = $row['matchId'];
            $matchKey = $matchId instanceof Uuid ? $matchId->toRfc4122() : (string) $matchId;
            $matchIds[$matchKey] = true;

            $kickoff = $row['kickoffAt']->setTimezone(PragueCalendar::timezone());

            if ($kickoff >= $dayStart && $kickoff < $dayEnd) {
                $todayIds[$matchKey] = true;
            }

            if (SportMatchState::Live === $row['state']) {
                $competitionId = $row['competitionId'];
                $liveCompetitions[$competitionId instanceof Uuid ? $competitionId->toRfc4122() : (string) $competitionId] = true;
            }
        }

        return [
            'total' => count($matchIds),
            'today' => count($todayIds),
            'liveCompetitions' => count($liveCompetitions),
        ];
    }
}
