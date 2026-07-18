<?php

declare(strict_types=1);

namespace App\Query\GetMyGuessesInMatchSource;

use App\Repository\GuessRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetMyGuessesInMatchSourceQuery
{
    public function __construct(
        private GuessRepository $guessRepository,
    ) {
    }

    /**
     * @return list<MyGuessRowItem>
     */
    public function __invoke(GetMyGuessesInMatchSource $query): array
    {
        $guesses = $this->guessRepository->listActiveByUserInMatchSource(
            $query->userId,
            $query->matchSourceId,
            $query->competitionId,
        );

        return array_map(
            static fn ($g): MyGuessRowItem => new MyGuessRowItem(
                sportMatchId: $g->sportMatch->id,
                homeTeam: $g->sportMatch->homeTeam,
                awayTeam: $g->sportMatch->awayTeam,
                kickoffAt: $g->sportMatch->kickoffAt,
                state: $g->sportMatch->state,
                actualHomeScore: $g->sportMatch->homeScore,
                actualAwayScore: $g->sportMatch->awayScore,
                myHomeScore: $g->homeScore,
                myAwayScore: $g->awayScore,
            ),
            $guesses,
        );
    }
}
