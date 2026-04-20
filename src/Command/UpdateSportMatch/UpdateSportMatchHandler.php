<?php

declare(strict_types=1);

namespace App\Command\UpdateSportMatch;

use App\Repository\SportMatchRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateSportMatchHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateSportMatchCommand $command): void
    {
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $sportMatch->updateDetails(
            homeTeam: $command->homeTeam,
            awayTeam: $command->awayTeam,
            kickoffAt: $command->kickoffAt,
            venue: $command->venue,
            now: $now,
        );
    }
}
