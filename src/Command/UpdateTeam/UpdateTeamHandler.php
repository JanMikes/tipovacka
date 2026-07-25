<?php

declare(strict_types=1);

namespace App\Command\UpdateTeam;

use App\Repository\TeamRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateTeamHandler
{
    public function __construct(
        private TeamRepository $teamRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateTeamCommand $command): void
    {
        $team = $this->teamRepository->get($command->teamId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $team->updateDetails(
            name: $command->name,
            shortName: $command->shortName,
            country: null !== $command->country ? mb_strtoupper($command->country) : null,
            brandColor: null !== $command->brandColor ? mb_strtoupper($command->brandColor) : null,
            now: $now,
        );
    }
}
