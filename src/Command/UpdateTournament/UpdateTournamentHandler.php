<?php

declare(strict_types=1);

namespace App\Command\UpdateTournament;

use App\Exception\TournamentAlreadyFinished;
use App\Repository\TournamentRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateTournamentHandler
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateTournamentCommand $command): void
    {
        $tournament = $this->tournamentRepository->get($command->tournamentId);

        if ($tournament->isFinished) {
            throw TournamentAlreadyFinished::withId($tournament->id);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $tournament->updateDetails(
            name: $command->name,
            description: $command->description,
            startAt: $command->startAt,
            endAt: $command->endAt,
            now: $now,
        );

        if ($command->updateCreationPin) {
            $tournament->setCreationPin($command->creationPin, $now);
        }
    }
}
