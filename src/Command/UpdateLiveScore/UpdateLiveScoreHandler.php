<?php

declare(strict_types=1);

namespace App\Command\UpdateLiveScore;

use App\Repository\SportMatchRepository;
use App\Service\SportMatch\MatchEventWriter;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * In-progress score update. Records SportMatchLiveScoreChanged only — guess
 * evaluation is triggered exclusively by the final score.
 */
#[AsMessageHandler]
final readonly class UpdateLiveScoreHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private MatchEventWriter $matchEventWriter,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateLiveScoreCommand $command): void
    {
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $sportMatch->updateLiveScore(
            homeScore: $command->homeScore,
            awayScore: $command->awayScore,
            periodScores: $command->periodScores,
            now: $now,
        );

        $this->matchEventWriter->replace($sportMatch, $command->events, $now);
    }
}
