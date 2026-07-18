<?php

declare(strict_types=1);

namespace App\Command\BulkImportSportMatches;

use App\Repository\MatchSourceRepository;
use App\Service\SportMatch\SportMatchImporter;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BulkImportSportMatchesHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private SportMatchImporter $importer,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(BulkImportSportMatchesCommand $command): int
    {
        $matchSource = $this->matchSourceRepository->get($command->matchSourceId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        return $this->importer->commit(
            matchSource: $matchSource,
            rows: $command->rows,
            now: $now,
        );
    }
}
