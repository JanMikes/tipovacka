<?php

declare(strict_types=1);

namespace App\Query\GetGuessesForMatchInGroup;

use App\Repository\GuessRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetGuessesForMatchInGroupQuery
{
    public function __construct(
        private GuessRepository $guessRepository,
    ) {
    }

    public function __invoke(GetGuessesForMatchInGroup $query): GuessesForMatchInGroupResult
    {
        $guesses = $this->guessRepository->listActiveByGroupAndMatch(
            $query->groupId,
            $query->sportMatchId,
        );

        $items = array_map(
            static fn ($g): GuessForMatchItem => new GuessForMatchItem(
                userId: $g->user->id,
                nickname: $g->user->displayName,
                homeScore: $g->homeScore,
                awayScore: $g->awayScore,
                submittedAt: $g->submittedAt,
                updatedAt: $g->updatedAt,
                isMine: $g->user->id->equals($query->viewerId),
            ),
            $guesses,
        );

        return new GuessesForMatchInGroupResult(items: $items);
    }
}
