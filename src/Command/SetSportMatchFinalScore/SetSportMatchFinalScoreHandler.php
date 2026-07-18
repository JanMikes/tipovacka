<?php

declare(strict_types=1);

namespace App\Command\SetSportMatchFinalScore;

use App\Repository\SportMatchRepository;
use App\Service\SportMatch\MatchEventWriter;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetSportMatchFinalScoreHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private MatchEventWriter $matchEventWriter,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SetSportMatchFinalScoreCommand $command): void
    {
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $sportMatch->setFinalScore(
            homeScore: $command->homeScore,
            awayScore: $command->awayScore,
            periodScores: $command->periodScores,
            overtimeHomeScore: $command->overtimeHomeScore,
            overtimeAwayScore: $command->overtimeAwayScore,
            now: $now,
        );

        $this->matchEventWriter->replace($sportMatch, $command->events, $now);

        if ($command->isLastMatch && !$sportMatch->matchSource->isCompleted) {
            $sportMatch->matchSource->markCompleted($now);
        }
    }
}
