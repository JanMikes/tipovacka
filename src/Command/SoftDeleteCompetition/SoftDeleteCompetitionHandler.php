<?php

declare(strict_types=1);

namespace App\Command\SoftDeleteCompetition;

use App\Repository\CompetitionRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SoftDeleteCompetitionHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SoftDeleteCompetitionCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $competition->softDelete(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
