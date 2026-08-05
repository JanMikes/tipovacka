<?php

declare(strict_types=1);

namespace App\Query\ListUserMatches;

use App\Entity\Competition;
use App\Entity\CompetitionSource;
use App\Entity\Guess;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Repository\SportMatchRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Competition\TipStatsProvider;
use App\Service\EffectiveTipDeadlineResolver;
use App\Value\TeamView;
use App\Value\TipStats;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListUserMatchesQuery
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private UserRepository $userRepository,
        private CompetitionMatchProvider $matchProvider,
        private TipStatsProvider $tipStatsProvider,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<UserMatchItem>
     */
    public function __invoke(ListUserMatches $query): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $matches = $this->sportMatchRepository->listAllForUser(
            userId: $query->userId,
            now: $now,
        );

        if (0 === count($matches)) {
            return [];
        }

        $user = $this->userRepository->get($query->userId);

        $matchSourceIds = [];
        $matchIds = [];

        foreach ($matches as $m) {
            $matchSourceIds[$m->matchSource->id->toRfc4122()] = $m->matchSource->id;
            $matchIds[] = $m->id;
        }

        $competitionsByMatchSource = $this->loadUserCompetitionsByMatchSource(
            $query->userId,
            array_values($matchSourceIds),
            $query->competitionId,
        );
        $guessesByMatch = $this->loadGuessesByMatch($query->userId, $matchIds);

        // Distribution stats are per (competition, match); collect the pairs while
        // walking the list and resolve them all in ONE batch afterwards.
        /** @var array<string, array{0: Competition, 1: list<SportMatch>}> $statsPairs */
        $statsPairs = [];
        /** @var list<array{match: SportMatch, competitions: list<Competition>, isTippable: bool, competitionsCount: int, guessedCompetitionsCount: int, openCompetitionsCount: int, pendingCompetitionsCount: int, competitionIds: list<Uuid>, pendingCompetitionIds: list<Uuid>, myTip: ?array{home: int, away: int}, waitingOpensAt: ?\DateTimeImmutable, waitingNote: ?string}> $rows */
        $rows = [];

        foreach ($matches as $m) {
            $matchSourceKey = $m->matchSource->id->toRfc4122();
            $matchKey = $m->id->toRfc4122();

            $includingCompetitions = $this->includingCompetitions($competitionsByMatchSource[$matchSourceKey] ?? [], $m);

            // A match that belongs to none of the user's competitions
            // (subset-excluded, playoff-excluded) is not theirs to tip.
            if (0 === count($includingCompetitions)) {
                continue;
            }

            foreach ($includingCompetitions as $competition) {
                $competitionKey = $competition->id->toRfc4122();
                $statsPairs[$competitionKey] ??= [$competition, []];
                $statsPairs[$competitionKey][1][] = $m;
            }

            $competitionIds = array_map(
                static fn (Competition $c): string => $c->id->toRfc4122(),
                $includingCompetitions,
            );

            // Per-competition locking: the match is tippable/pending only in
            // competitions where the resolver still has it open for this user.
            $openCompetitionIds = [];
            // …and „not open" has two very different causes. A window that has
            // not STARTED yet is a promise, not a closed door, so the earliest
            // opening among the soutěže that are merely waiting travels with the
            // row — the card then says „Tipování otevřeme…" instead of „Uzamčeno".
            $waitingOpensAt = null;
            $waitingNote = null;

            foreach ($includingCompetitions as $competition) {
                $window = $this->deadlineResolver->windowFor($competition, $m, $user);

                if ($m->isOpenForGuesses && $window->isOpen($now)) {
                    $openCompetitionIds[] = $competition->id->toRfc4122();

                    continue;
                }

                if ($m->isOpenForGuesses && $window->isWaiting($now)
                    && (null === $waitingOpensAt || $window->opensAt < $waitingOpensAt)
                ) {
                    $waitingOpensAt = $window->opensAt;
                    $waitingNote = $window->openingNote;
                }
            }

            $guessesHere = $guessesByMatch[$matchKey] ?? [];
            $guessedCompetitionIds = array_keys($guessesHere);

            // „Můj tip" is only unambiguous when exactly ONE of the user's competitions
            // includes the match — two competitions can hold two different tips for it.
            // Scoping the query (`competitionId`) always produces that single answer.
            $myTip = 1 === count($includingCompetitions)
                ? ($guessesHere[$competitionIds[0]] ?? null)
                : null;

            // Item 22: every match link is soutěž-scoped, so the row carries WHICH
            // soutěže — the first including one for the card, the first one still
            // missing a tip for „Zadat tip".
            $pendingCompetitionIds = array_values(array_diff($openCompetitionIds, $guessedCompetitionIds));

            $rows[] = [
                'match' => $m,
                'competitions' => $includingCompetitions,
                'isTippable' => [] !== $openCompetitionIds,
                'competitionsCount' => count($competitionIds),
                'guessedCompetitionsCount' => count(array_intersect($competitionIds, $guessedCompetitionIds)),
                'openCompetitionsCount' => count($openCompetitionIds),
                'pendingCompetitionsCount' => count($pendingCompetitionIds),
                'competitionIds' => array_map(Uuid::fromString(...), $competitionIds),
                'pendingCompetitionIds' => array_map(Uuid::fromString(...), $pendingCompetitionIds),
                'myTip' => $myTip,
                // Waiting only when NOTHING is tippable: a match open in one
                // soutěž and waiting in another is simply tippable — the card
                // links there, and the waiting one is reached from its strip.
                'waitingOpensAt' => [] === $openCompetitionIds ? $waitingOpensAt : null,
                'waitingNote' => [] === $openCompetitionIds ? $waitingNote : null,
            ];
        }

        $stats = $this->tipStatsProvider->forPairs(array_values($statsPairs), $user);
        $items = [];

        foreach ($rows as $row) {
            $m = $row['match'];

            $items[] = new UserMatchItem(
                id: $m->id,
                matchSourceId: $m->matchSource->id,
                matchSourceName: $m->matchSource->name,
                homeTeam: TeamView::fromTeam($m->homeTeam),
                awayTeam: TeamView::fromTeam($m->awayTeam),
                kickoffAt: $m->kickoffAt,
                venue: $m->venue,
                round: $m->round,
                isPlayoff: $m->isPlayoff,
                isOpenForGuesses: $m->isOpenForGuesses,
                isFinished: $m->isFinished,
                isLive: $m->isLive,
                isPostponed: $m->isPostponed,
                homeScore: $m->homeScore,
                awayScore: $m->awayScore,
                isTippable: $row['isTippable'],
                competitionsCount: $row['competitionsCount'],
                guessedCompetitionsCount: $row['guessedCompetitionsCount'],
                openCompetitionsCount: $row['openCompetitionsCount'],
                pendingCompetitionsCount: $row['pendingCompetitionsCount'],
                competitionIds: $row['competitionIds'],
                pendingCompetitionIds: $row['pendingCompetitionIds'],
                myHomeScore: $row['myTip']['home'] ?? null,
                myAwayScore: $row['myTip']['away'] ?? null,
                tipStats: $this->statsFor($stats, $m, $row['competitions']),
                opensAt: $row['waitingOpensAt'],
                openingNote: $row['waitingNote'],
            );
        }

        return $items;
    }

    /**
     * @param array<string, TipStats> $stats
     * @param list<Competition>       $competitions
     *
     * @return list<TipStats>
     */
    private function statsFor(array $stats, SportMatch $match, array $competitions): array
    {
        $result = [];

        foreach ($competitions as $competition) {
            $entry = $stats[$this->tipStatsProvider->key($competition->id, $match->id)] ?? null;

            if (null !== $entry) {
                $result[] = $entry;
            }
        }

        return $result;
    }

    /**
     * @param list<Competition> $competitions
     *
     * @return list<Competition> the competitions that include the match
     */
    private function includingCompetitions(array $competitions, SportMatch $match): array
    {
        return array_values(array_filter(
            $competitions,
            fn (Competition $competition): bool => $this->matchProvider->includes($competition, $match),
        ));
    }

    /**
     * @param list<Uuid> $matchSourceIds
     *
     * @return array<string, list<Competition>> keyed by match source UUID → the user's active competitions
     */
    private function loadUserCompetitionsByMatchSource(Uuid $userId, array $matchSourceIds, ?Uuid $competitionId): array
    {
        if (0 === count($matchSourceIds)) {
            return [];
        }

        $qb = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT g')
            ->from(Membership::class, 'm')
            ->innerJoin(Competition::class, 'g', 'WITH', 'g.id = m.competition')
            // Through ANY scope layer, not just the headline zdroj: a soutěž
            // drawing from three zdroje must surface under each of them.
            ->innerJoin(CompetitionSource::class, 'cs', 'WITH', 'cs.competition = g')
            ->where('m.user = :userId')
            ->andWhere('cs.matchSource IN (:matchSourceIds)')
            ->andWhere('m.leftAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('matchSourceIds', $matchSourceIds);

        // Scoping happens HERE, on the membership side: everything downstream
        // (which matches count as the user's, the tip-stats batch) then follows
        // from the single soutěž without a second pass over the rows.
        if (null !== $competitionId) {
            $qb->andWhere('g.id = :competitionId')->setParameter('competitionId', $competitionId);
        }

        /** @var list<Competition> $competitions */
        $competitions = $qb->getQuery()->getResult();

        $wanted = [];
        foreach ($matchSourceIds as $matchSourceId) {
            $wanted[$matchSourceId->toRfc4122()] = true;
        }

        $byMatchSource = [];
        $seen = [];

        foreach ($competitions as $competition) {
            if (isset($seen[$competition->id->toRfc4122()])) {
                continue;
            }
            $seen[$competition->id->toRfc4122()] = true;

            foreach ($competition->sources as $layer) {
                $sourceKey = $layer->matchSource->id->toRfc4122();

                if (isset($wanted[$sourceKey])) {
                    $byMatchSource[$sourceKey][] = $competition;
                }
            }
        }

        return $byMatchSource;
    }

    /**
     * The user's own tips on these matches, in ONE query — never per row.
     *
     * @param list<Uuid> $matchIds
     *
     * @return array<string, array<string, array{home: int, away: int}>> sport match UUID → competition UUID → tip
     */
    private function loadGuessesByMatch(Uuid $userId, array $matchIds): array
    {
        if (0 === count($matchIds)) {
            return [];
        }

        /** @var list<array{sportMatchId: string, competitionId: string, homeScore: int, awayScore: int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(g.sportMatch) AS sportMatchId, IDENTITY(g.competition) AS competitionId, g.homeScore AS homeScore, g.awayScore AS awayScore')
            ->from(Guess::class, 'g')
            ->where('g.user = :userId')
            ->andWhere('g.sportMatch IN (:matchIds)')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('matchIds', $matchIds)
            ->getQuery()
            ->getArrayResult();

        $byMatch = [];
        foreach ($rows as $row) {
            $byMatch[(string) $row['sportMatchId']][(string) $row['competitionId']] = [
                'home' => $row['homeScore'],
                'away' => $row['awayScore'],
            ];
        }

        return $byMatch;
    }
}
