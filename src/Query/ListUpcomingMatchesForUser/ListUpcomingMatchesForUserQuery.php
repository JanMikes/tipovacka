<?php

declare(strict_types=1);

namespace App\Query\ListUpcomingMatchesForUser;

use App\Repository\SportMatchRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListUpcomingMatchesForUserQuery
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
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

        return array_map(
            static fn ($m): UpcomingMatchItem => new UpcomingMatchItem(
                id: $m->id,
                tournamentId: $m->tournament->id,
                tournamentName: $m->tournament->name,
                homeTeam: $m->homeTeam,
                awayTeam: $m->awayTeam,
                kickoffAt: $m->kickoffAt,
                venue: $m->venue,
            ),
            $matches,
        );
    }
}
