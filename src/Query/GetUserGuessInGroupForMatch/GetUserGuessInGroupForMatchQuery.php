<?php

declare(strict_types=1);

namespace App\Query\GetUserGuessInGroupForMatch;

use App\Repository\GuessRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetUserGuessInGroupForMatchQuery
{
    public function __construct(
        private GuessRepository $guessRepository,
    ) {
    }

    public function __invoke(GetUserGuessInGroupForMatch $query): ?UserGuessResult
    {
        $guess = $this->guessRepository->findActiveByUserMatchGroup(
            $query->userId,
            $query->sportMatchId,
            $query->groupId,
        );

        if (null === $guess) {
            return null;
        }

        return new UserGuessResult(
            guessId: $guess->id,
            homeScore: $guess->homeScore,
            awayScore: $guess->awayScore,
            submittedAt: $guess->submittedAt,
            updatedAt: $guess->updatedAt,
        );
    }
}
