<?php

declare(strict_types=1);

namespace App\Query\ListMyPlayingCompetitions;

use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\GuessEvaluation;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\SportMatchState;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Cost is O(competitions the viewer is in) — the same order as the cards it
 * feeds — never O(matches): the standings, the round labels and the round gains
 * are four cross-competition aggregates, and the only per-competition work is
 * the match-scope membership test (cached in {@see CompetitionMatchProvider})
 * plus one batched deadline resolution.
 */
#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListMyPlayingCompetitionsQuery
{
    public function __construct(
        private MembershipRepository $membershipRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private CompetitionMatchProvider $matchProvider,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<PlayingCompetitionItem>
     */
    public function __invoke(ListMyPlayingCompetitions $query): array
    {
        $memberships = $this->membershipRepository->findMyActive($query->userId);

        if ([] === $memberships) {
            return [];
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $user = $this->userRepository->get($query->userId);

        $competitions = array_map(static fn (Membership $m): Competition => $m->competition, $memberships);
        $competitionIds = array_map(static fn (Competition $c): Uuid => $c->id, $competitions);

        $memberCounts = $this->memberCounts($competitionIds);
        $pointsByCompetitionAndUser = $this->pointsByCompetitionAndUser($competitionIds);
        $roundPoints = $this->viewerPointsByCompetitionAndRound($query->userId, $competitionIds);
        $matchesBySource = $this->matchesForSourcesOf($competitions);
        $guessedPairs = $this->guessedPairs($query->userId, $competitionIds);

        $items = [];

        foreach ($competitions as $competition) {
            $key = $competition->id->toRfc4122();
            $sourceKey = $competition->matchSource->id->toRfc4122();

            $included = array_values(array_filter(
                $matchesBySource[$sourceKey] ?? [],
                fn (SportMatch $m): bool => $this->matchProvider->includes($competition, $m),
            ));

            $currentRound = $this->currentRound($included, $now);
            $pending = $this->pendingTips($competition, $included, $guessedPairs[$key] ?? [], $user, $now);
            $viewerPoints = $pointsByCompetitionAndUser[$key][$query->userId->toRfc4122()] ?? 0;

            $rank = 1;

            foreach ($pointsByCompetitionAndUser[$key] ?? [] as $points) {
                if ($points > $viewerPoints) {
                    ++$rank;
                }
            }

            $items[] = new PlayingCompetitionItem(
                competitionId: $competition->id,
                name: $competition->name,
                matchSourceName: $competition->matchSource->name,
                viewerIsOwner: $competition->owner->id->equals($query->userId),
                isFinished: $competition->matchSource->isCompleted || $this->allSettled($included),
                rank: $rank,
                memberCount: $memberCounts[$key] ?? 1,
                totalPoints: $viewerPoints,
                roundPoints: null !== $currentRound ? ($roundPoints[$key][$currentRound] ?? 0) : 0,
                currentRound: $currentRound,
                liveMatchCount: count(array_filter($included, static fn (SportMatch $m): bool => $m->isLive)),
                pendingTipCount: $pending['count'],
                nextDeadlineAt: $pending['deadline'],
                nextKickoffAt: $this->nextKickoff($included, $now),
            );
        }

        return $items;
    }

    /**
     * @param list<SportMatch>    $included
     * @param array<string, true> $guessedMatchIds
     *
     * @return array{count: int, deadline: \DateTimeImmutable|null}
     */
    private function pendingTips(
        Competition $competition,
        array $included,
        array $guessedMatchIds,
        User $user,
        \DateTimeImmutable $now,
    ): array {
        $open = array_values(array_filter(
            $included,
            static fn (SportMatch $m): bool => $m->isOpenForGuesses && !isset($guessedMatchIds[$m->id->toRfc4122()]),
        ));

        if ([] === $open) {
            return ['count' => 0, 'deadline' => null];
        }

        $deadlines = $this->deadlineResolver->deadlinesFor($competition, $open, $user);

        $count = 0;
        $earliest = null;

        foreach ($open as $match) {
            $deadline = $deadlines[$match->id->toRfc4122()] ?? null;

            if (null === $deadline || $now >= $deadline) {
                continue;
            }

            ++$count;

            if (null === $earliest || $deadline < $earliest) {
                $earliest = $deadline;
            }
        }

        return ['count' => $count, 'deadline' => $earliest];
    }

    /**
     * @param list<SportMatch> $included
     */
    private function allSettled(array $included): bool
    {
        if ([] === $included) {
            return false;
        }

        foreach ($included as $match) {
            if ($match->isScheduled || $match->isLive || $match->isPostponed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Same rule as {@see \App\Service\Competition\CompetitionRoundResolver}: the
     * latest kicked-off labelled match, else the earliest upcoming one — applied
     * to the already-loaded match list so it costs no extra statement.
     *
     * @param list<SportMatch> $included
     */
    private function currentRound(array $included, \DateTimeImmutable $now): ?string
    {
        $started = null;
        $upcoming = null;

        foreach ($included as $match) {
            if (null === $match->round || $match->isCancelled) {
                continue;
            }

            if ($match->kickoffAt <= $now) {
                if (null === $started || $match->kickoffAt > $started->kickoffAt) {
                    $started = $match;
                }

                continue;
            }

            if (null === $upcoming || $match->kickoffAt < $upcoming->kickoffAt) {
                $upcoming = $match;
            }
        }

        return null !== $started ? $started->round : $upcoming?->round;
    }

    /**
     * @param list<SportMatch> $included
     */
    private function nextKickoff(array $included, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        $next = null;

        foreach ($included as $match) {
            if ($match->isCancelled || $match->kickoffAt <= $now) {
                continue;
            }

            if (null === $next || $match->kickoffAt < $next) {
                $next = $match->kickoffAt;
            }
        }

        return $next;
    }

    /**
     * @param list<Uuid> $competitionIds
     *
     * @return array<string, int>
     */
    private function memberCounts(array $competitionIds): array
    {
        /** @var list<array{competitionId: string, members: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(m.competition) AS competitionId', 'COUNT(m.id) AS members')
            ->from(Membership::class, 'm')
            ->where('m.competition IN (:competitionIds)')
            ->andWhere('m.leftAt IS NULL')
            ->groupBy('m.competition')
            ->setParameter('competitionIds', $competitionIds)
            ->getQuery()
            ->getArrayResult();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row['competitionId']] = (int) $row['members'];
        }

        return $counts;
    }

    /**
     * @param list<Uuid> $competitionIds
     *
     * @return array<string, array<string, int>> competition UUID → user UUID → points
     */
    private function pointsByCompetitionAndUser(array $competitionIds): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(g.competition) AS competitionId', 'IDENTITY(g.user) AS userId', 'SUM(e.totalPoints) AS points')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin(Guess::class, 'g', 'WITH', 'g.id = e.guess')
            ->innerJoin(SportMatch::class, 'm', 'WITH', 'm.id = g.sportMatch')
            ->innerJoin(Competition::class, 'c', 'WITH', 'c.id = g.competition')
            ->where('g.competition IN (:lpc_competitionIds)')
            ->andWhere('g.deletedAt IS NULL')
            ->andWhere('m.deletedAt IS NULL')
            ->groupBy('g.competition')
            ->addGroupBy('g.user')
            ->setParameter('lpc_competitionIds', $competitionIds);

        $this->matchProvider->applyRowLevelCompetitionMatchFilter($qb, 'm', 'c');

        /** @var list<array{competitionId: string, userId: string, points: int|string|null}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        $points = [];

        foreach ($rows as $row) {
            $points[$row['competitionId']][$row['userId']] = (int) ($row['points'] ?? 0);
        }

        return $points;
    }

    /**
     * @param list<Uuid> $competitionIds
     *
     * @return array<string, array<string, int>> competition UUID → round label → points
     */
    private function viewerPointsByCompetitionAndRound(Uuid $userId, array $competitionIds): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(g.competition) AS competitionId', 'm.round AS round', 'SUM(e.totalPoints) AS points')
            ->from(GuessEvaluation::class, 'e')
            ->innerJoin(Guess::class, 'g', 'WITH', 'g.id = e.guess')
            ->innerJoin(SportMatch::class, 'm', 'WITH', 'm.id = g.sportMatch')
            ->innerJoin(Competition::class, 'c', 'WITH', 'c.id = g.competition')
            ->where('g.competition IN (:lpc_competitionIds)')
            ->andWhere('g.user = :lpc_userId')
            ->andWhere('g.deletedAt IS NULL')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.round IS NOT NULL')
            ->groupBy('g.competition')
            ->addGroupBy('m.round')
            ->setParameter('lpc_competitionIds', $competitionIds)
            ->setParameter('lpc_userId', $userId);

        $this->matchProvider->applyRowLevelCompetitionMatchFilter($qb, 'm', 'c');

        /** @var list<array{competitionId: string, round: string, points: int|string|null}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        $points = [];

        foreach ($rows as $row) {
            $points[$row['competitionId']][$row['round']] = (int) ($row['points'] ?? 0);
        }

        return $points;
    }

    /**
     * @param list<Competition> $competitions
     *
     * @return array<string, list<SportMatch>> match source UUID → its matches
     */
    private function matchesForSourcesOf(array $competitions): array
    {
        $sourceIds = [];

        foreach ($competitions as $competition) {
            $sourceIds[$competition->matchSource->id->toRfc4122()] = $competition->matchSource->id;
        }

        /** @var list<SportMatch> $matches */
        $matches = $this->entityManager->createQueryBuilder()
            ->select('m', 'ht', 'at')
            ->from(SportMatch::class, 'm')
            ->innerJoin('m.homeTeam', 'ht')
            ->innerJoin('m.awayTeam', 'at')
            ->where('m.matchSource IN (:sourceIds)')
            ->andWhere('m.deletedAt IS NULL')
            ->andWhere('m.state != :cancelled')
            ->orderBy('m.kickoffAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->setParameter('sourceIds', array_values($sourceIds))
            ->setParameter('cancelled', SportMatchState::Cancelled)
            ->getQuery()
            ->getResult();

        $bySource = [];

        foreach ($matches as $match) {
            $bySource[$match->matchSource->id->toRfc4122()][] = $match;
        }

        return $bySource;
    }

    /**
     * @param list<Uuid> $competitionIds
     *
     * @return array<string, array<string, true>> competition UUID → tipped match UUID set
     */
    private function guessedPairs(Uuid $userId, array $competitionIds): array
    {
        /** @var list<array{competitionId: string, sportMatchId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(g.competition) AS competitionId', 'IDENTITY(g.sportMatch) AS sportMatchId')
            ->from(Guess::class, 'g')
            ->where('g.competition IN (:competitionIds)')
            ->andWhere('g.user = :userId')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('competitionIds', $competitionIds)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getArrayResult();

        $pairs = [];

        foreach ($rows as $row) {
            $pairs[$row['competitionId']][$row['sportMatchId']] = true;
        }

        return $pairs;
    }
}
