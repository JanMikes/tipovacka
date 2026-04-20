<?php

declare(strict_types=1);

namespace App\Command\SetSportMatchFinalScore;

use App\Exception\InvalidScore;
use App\Repository\SportMatchRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetSportMatchFinalScoreHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SetSportMatchFinalScoreCommand $command): void
    {
        if ($command->homeScore < 0 || $command->awayScore < 0) {
            throw InvalidScore::negative();
        }

        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $sportMatch->setFinalScore(
            homeScore: $command->homeScore,
            awayScore: $command->awayScore,
            now: $now,
        );
    }
}
