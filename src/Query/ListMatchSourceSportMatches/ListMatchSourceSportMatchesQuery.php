<?php

declare(strict_types=1);

namespace App\Query\ListMatchSourceSportMatches;

use App\Repository\SportMatchRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListMatchSourceSportMatchesQuery
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
    ) {
    }

    /**
     * @return list<SportMatchListItem>
     */
    public function __invoke(ListMatchSourceSportMatches $query): array
    {
        $matches = $this->sportMatchRepository->listByMatchSource(
            matchSourceId: $query->matchSourceId,
            state: $query->state,
            from: $query->from,
            to: $query->to,
        );

        return array_map(
            static fn ($m): SportMatchListItem => new SportMatchListItem(
                id: $m->id,
                matchSourceId: $m->matchSource->id,
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
