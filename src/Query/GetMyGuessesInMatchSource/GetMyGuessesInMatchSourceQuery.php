<?php

declare(strict_types=1);

namespace App\Query\GetMyGuessesInMatchSource;

use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Service\Competition\CompetitionMatchProvider;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetMyGuessesInMatchSourceQuery
{
    public function __construct(
        private GuessRepository $guessRepository,
        private CompetitionRepository $competitionRepository,
        private CompetitionMatchProvider $matchProvider,
    ) {
    }

    /**
     * @return list<MyGuessRowItem>
     */
    public function __invoke(GetMyGuessesInMatchSource $query): array
    {
        $competition = $this->competitionRepository->get($query->competitionId);

        $guesses = $this->guessRepository->listActiveByUserInMatchSource(
            $query->userId,
            $query->matchSourceId,
            $query->competitionId,
        );

        // Guesses for matches the competition no longer includes (subset
        // unselected, playoff excluded) stop counting and are hidden.
        $guesses = array_values(array_filter(
            $guesses,
            fn ($g): bool => $this->matchProvider->includes($competition, $g->sportMatch),
        ));

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
