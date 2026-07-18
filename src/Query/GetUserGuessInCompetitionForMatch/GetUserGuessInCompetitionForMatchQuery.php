<?php

declare(strict_types=1);

namespace App\Query\GetUserGuessInCompetitionForMatch;

use App\Repository\GuessRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetUserGuessInCompetitionForMatchQuery
{
    public function __construct(
        private GuessRepository $guessRepository,
    ) {
    }

    public function __invoke(GetUserGuessInCompetitionForMatch $query): ?UserGuessResult
    {
        $guess = $this->guessRepository->findActiveByUserMatchCompetition(
            $query->userId,
            $query->sportMatchId,
            $query->competitionId,
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
