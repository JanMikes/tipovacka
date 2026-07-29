<?php

declare(strict_types=1);

namespace App\Query\GetCompetitionCurrentRound;

use App\Repository\CompetitionRepository;
use App\Service\Competition\CompetitionRoundResolver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetCompetitionCurrentRoundQuery
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private CompetitionRoundResolver $roundResolver,
    ) {
    }

    public function __invoke(GetCompetitionCurrentRound $query): CompetitionCurrentRoundResult
    {
        $competition = $this->competitionRepository->get($query->competitionId);
        $round = $this->roundResolver->currentRound($competition);

        if (null === $round) {
            return new CompetitionCurrentRoundResult(round: null, matchCount: 0, finishedMatchCount: 0);
        }

        $progress = $this->roundResolver->roundProgress($competition, $round);

        return new CompetitionCurrentRoundResult(
            round: $round,
            matchCount: $progress['matchCount'],
            finishedMatchCount: $progress['finishedMatchCount'],
        );
    }
}
