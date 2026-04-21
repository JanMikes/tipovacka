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

        $tournamentIds = [];
        $matchIds = [];

        foreach ($matches as $m) {
            $tournamentIds[$m->tournament->id->toRfc4122()] = $m->tournament->id;
            $matchIds[] = $m->id;
        }

        $groupsByTournament = $this->loadUserGroupsByTournament($query->userId, array_values($tournamentIds));
        $guessedGroupsByMatch = $this->loadGuessedGroupsByMatch($query->userId, $matchIds);

        return array_map(
            static function ($m) use ($groupsByTournament, $guessedGroupsByMatch): UpcomingMatchItem {
                $tournamentKey = $m->tournament->id->toRfc4122();
                $matchKey = $m->id->toRfc4122();

                $groupIds = $groupsByTournament[$tournamentKey] ?? [];
                $guessedGroupIds = $guessedGroupsByMatch[$matchKey] ?? [];

                $groupsCount = count($groupIds);
                $guessedGroupsCount = count(array_intersect($groupIds, $guessedGroupIds));

                return new UpcomingMatchItem(
                    id: $m->id,
                    tournamentId: $m->tournament->id,
                    tournamentName: $m->tournament->name,
                    homeTeam: $m->homeTeam,
                    awayTeam: $m->awayTeam,
                    kickoffAt: $m->kickoffAt,
                    venue: $m->venue,
                    groupsCount: $groupsCount,
                    guessedGroupsCount: $guessedGroupsCount,
                    pendingGroupsCount: $groupsCount - $guessedGroupsCount,
                );
            },
            $matches,
        );
    }

    /**
     * @param list<Uuid> $tournamentIds
     *
     * @return array<string, list<string>> keyed by tournament UUID → list of group UUIDs
     */
    private function loadUserGroupsByTournament(Uuid $userId, array $tournamentIds): array
    {
        if (0 === count($tournamentIds)) {
            return [];
        }

        /** @var list<array{tournamentId: string, groupId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('t.id AS tournamentId, g.id AS groupId')
            ->from(Membership::class, 'm')
            ->innerJoin('m.group', 'g')
            ->innerJoin('g.tournament', 't')
            ->where('m.user = :userId')
            ->andWhere('t.id IN (:tournamentIds)')
            ->andWhere('m.leftAt IS NULL')
            ->andWhere('g.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('tournamentIds', $tournamentIds)
            ->getQuery()
            ->getArrayResult();

        $byTournament = [];
        foreach ($rows as $row) {
            $tournamentKey = (string) $row['tournamentId'];
            $byTournament[$tournamentKey][] = (string) $row['groupId'];
        }

        return $byTournament;
    }

    /**
     * @param list<Uuid> $matchIds
     *
     * @return array<string, list<string>> keyed by sport match UUID → list of group UUIDs where user has guessed
     */
    private function loadGuessedGroupsByMatch(Uuid $userId, array $matchIds): array
    {
        if (0 === count($matchIds)) {
            return [];
        }

        /** @var list<array{sportMatchId: string, groupId: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(g.sportMatch) AS sportMatchId, IDENTITY(g.group) AS groupId')
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
            $byMatch[$matchKey][] = (string) $row['groupId'];
        }

        return $byMatch;
    }
}
