<?php

declare(strict_types=1);

namespace App\Command\BulkImportSportMatches;

use App\Repository\TournamentRepository;
use App\Service\SportMatch\SportMatchImporter;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BulkImportSportMatchesHandler
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private SportMatchImporter $importer,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(BulkImportSportMatchesCommand $command): int
    {
        $tournament = $this->tournamentRepository->get($command->tournamentId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        return $this->importer->commit(
            tournament: $tournament,
            rows: $command->rows,
            now: $now,
        );
    }
}
