<?php

declare(strict_types=1);

namespace App\Command\SoftDeleteTournament;

use App\Repository\TournamentRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SoftDeleteTournamentHandler
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SoftDeleteTournamentCommand $command): void
    {
        $tournament = $this->tournamentRepository->get($command->tournamentId);
        $tournament->softDelete(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
