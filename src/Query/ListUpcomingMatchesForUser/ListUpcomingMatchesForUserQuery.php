<?php

declare(strict_types=1);

namespace App\Query\ListUpcomingMatchesForUser;

use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\Membership;
use App\Entity\SportMatch;
use App\Repository\SportMatchRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListUpcomingMatchesForUserQuery
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private UserRepository $userRepository,
        private CompetitionMatchProvider $matchProvider,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<UpcomingMatchItem>
     */
    public function __invoke(ListUpcomingMatchesForUser $query): array
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $matches = $this->sportMatchRepository->listUpcomingForUser(
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

        $competitionsByMatchSource = $this->loadUserCompetitionsByMatchSource($query->userId, array_values($matchSourceIds));
        $guessedCompetitionsByMatch = $this->loadGuessedCompetitionsByMatch($query->userId, $matchIds);

        $items = [];

        foreach ($matches as $m) {
            $matchSourceKey = $m->matchSource->id->toRfc4122();
            $matchKey = $m->id->toRfc4122();

            $includingCompetitions = $this->includingCompetitions($competitionsByMatchSource[$matchSourceKey] ?? [], $m);

            // A match that belongs to none of the user's competitions
            // (subset-excluded, playoff-excluded) is not theirs to tip.
            if (0 === count($includingCompetitions)) {
                continue;
            }

            $competitionIds = array_map(
                static fn (Competition $c): string => $c->id->toRfc4122(),
                $includingCompetitions,
            );

            // "Chybí tip" pills only count competitions where the tip can still
            // be filled — per-competition locking via the deadline resolver.
            $openCompetitionIds = [];

            foreach ($includingCompetitions as $competition) {
                if (!$this->deadlineResolver->isLocked($competition, $m, $user, $now)) {
                    $openCompetitionIds[] = $competition->id->toRfc4122();
                }
            }

            $guessedCompetitionIds = $guessedCompetitionsByMatch[$matchKey] ?? [];

            $items[] = new UpcomingMatchItem(
                id: $m->id,
                matchSourceId: $m->matchSource->id,
                matchSourceName: $m->matchSource->name,
                homeTeam: $m->homeTeam,
                awayTeam: $m->awayTeam,
                kickoffAt: $m->kickoffAt,
                venue: $m->venue,
                round: $m->round,
                isPlayoff: $m->isPlayoff,
                competitionsCount: count($competitionIds),
                guessedCompetitionsCount: count(array_intersect($competitionIds, $guessedCompetitionIds)),
                openCompetitionsCount: count($openCompetitionIds),
                pendingCompetitionsCount: count(array_diff($openCompetitionIds, $guessedCompetitionIds)),
            );
        }

        return $items;
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
    private function loadUserCompetitionsByMatchSource(Uuid $userId, array $matchSourceIds): array
    {
        if (0 === count($matchSourceIds)) {
            return [];
        }

        /** @var list<Competition> $competitions */
        $competitions = $this->entityManager->createQueryBuilder()
            ->select('g')
            ->from(Membership::class, 'm')
            ->innerJoin(Competition::class, 'g', 'WITH', 'g.id = m.competition')
            ->where('m.user = :userId')
            ->andWhere('g.matchSource IN (:matchSourceIds)')
            ->andWhere('m.leftAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('matchSourceIds', $matchSourceIds)
            ->getQuery()
            ->getResult();

        $byMatchSource = [];
        foreach ($competitions as $competition) {
            $byMatchSource[$competition->matchSource->id->toRfc4122()][] = $competition;
        }

        return $byMatchSource;
    }

    /**
     * @param list<Uuid> $matchIds
     *
     * @return array<string, list<string>> keyed by sport match UUID → list of competition UUIDs where user has guessed
     */
    private function loadGuessedCompetitionsByMatch(Uuid $userId, array $matchIds): array
    {
        if (0 === count($matchIds)) {
            return [];
        }

        /** @var list<array{sportMatchId: string, competitionId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(g.sportMatch) AS sportMatchId, IDENTITY(g.competition) AS competitionId')
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
            $matchKey = (string) $row['sportMatchId'];
            $byMatch[$matchKey][] = (string) $row['competitionId'];
        }

        return $byMatch;
    }
}
