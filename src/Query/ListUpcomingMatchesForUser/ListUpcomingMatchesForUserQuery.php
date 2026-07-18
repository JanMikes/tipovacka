<?php

declare(strict_types=1);

namespace App\Query\ListUpcomingMatchesForUser;

use App\Entity\Guess;
use App\Entity\Membership;
use App\Repository\SportMatchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListUpcomingMatchesForUserQuery
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
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

        $matchSourceIds = [];
        $matchIds = [];

        foreach ($matches as $m) {
            $matchSourceIds[$m->matchSource->id->toRfc4122()] = $m->matchSource->id;
            $matchIds[] = $m->id;
        }

        $competitionsByMatchSource = $this->loadUserCompetitionsByMatchSource($query->userId, array_values($matchSourceIds));
        $guessedCompetitionsByMatch = $this->loadGuessedCompetitionsByMatch($query->userId, $matchIds);

        return array_map(
            static function ($m) use ($competitionsByMatchSource, $guessedCompetitionsByMatch): UpcomingMatchItem {
                $matchSourceKey = $m->matchSource->id->toRfc4122();
                $matchKey = $m->id->toRfc4122();

                $competitionIds = $competitionsByMatchSource[$matchSourceKey] ?? [];
                $guessedCompetitionIds = $guessedCompetitionsByMatch[$matchKey] ?? [];

                $competitionsCount = count($competitionIds);
                $guessedCompetitionsCount = count(array_intersect($competitionIds, $guessedCompetitionIds));

                return new UpcomingMatchItem(
                    id: $m->id,
                    matchSourceId: $m->matchSource->id,
                    matchSourceName: $m->matchSource->name,
                    homeTeam: $m->homeTeam,
                    awayTeam: $m->awayTeam,
                    kickoffAt: $m->kickoffAt,
                    venue: $m->venue,
                    round: $m->round,
                    competitionsCount: $competitionsCount,
                    guessedCompetitionsCount: $guessedCompetitionsCount,
                    pendingCompetitionsCount: $competitionsCount - $guessedCompetitionsCount,
                );
            },
            $matches,
        );
    }

    /**
     * @param list<Uuid> $matchSourceIds
     *
     * @return array<string, list<string>> keyed by match source UUID → list of competition UUIDs
     */
    private function loadUserCompetitionsByMatchSource(Uuid $userId, array $matchSourceIds): array
    {
        if (0 === count($matchSourceIds)) {
            return [];
        }

        /** @var list<array{matchSourceId: string, competitionId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('t.id AS matchSourceId, g.id AS competitionId')
            ->from(Membership::class, 'm')
            ->innerJoin('m.competition', 'g')
            ->innerJoin('g.matchSource', 't')
            ->where('m.user = :userId')
            ->andWhere('t.id IN (:matchSourceIds)')
            ->andWhere('m.leftAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('matchSourceIds', $matchSourceIds)
            ->getQuery()
            ->getArrayResult();

        $byMatchSource = [];
        foreach ($rows as $row) {
            $matchSourceKey = (string) $row['matchSourceId'];
            $byMatchSource[$matchSourceKey][] = (string) $row['competitionId'];
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
