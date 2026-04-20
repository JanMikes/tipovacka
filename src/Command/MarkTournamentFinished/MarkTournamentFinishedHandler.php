<?php

declare(strict_types=1);

namespace App\Command\MarkTournamentFinished;

use App\Repository\TournamentRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MarkTournamentFinishedHandler
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(MarkTournamentFinishedCommand $command): void
    {
        $tournament = $this->tournamentRepository->get($command->tournamentId);
        $tournament->markFinished(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
