<?php

declare(strict_types=1);

namespace App\Query\ListTournamentSportMatches;

use App\Repository\SportMatchRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListTournamentSportMatchesQuery
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
    ) {
    }

    /**
     * @return list<SportMatchListItem>
     */
    public function __invoke(ListTournamentSportMatches $query): array
    {
        $matches = $this->sportMatchRepository->listByTournament(
            tournamentId: $query->tournamentId,
            state: $query->state,
            from: $query->from,
            to: $query->to,
        );

        return array_map(
            static fn ($m): SportMatchListItem => new SportMatchListItem(
                id: $m->id,
                tournamentId: $m->tournament->id,
                homeTeam: $m->homeTeam,
                awayTeam: $m->awayTeam,
                kickoffAt: $m->kickoffAt,
                venue: $m->venue,
                state: $m->state,
                homeScore: $m->homeScore,
                awayScore: $m->awayScore,
            ),
            $matches,
        );
    }
}
