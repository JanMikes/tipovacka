<?php

declare(strict_types=1);

namespace App\Command\UpdateCompetition;

use App\Repository\CompetitionRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateCompetitionHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateCompetitionCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $competition->updateDetails(
            name: $command->name,
            description: $command->description,
            hideOthersTipsBeforeDeadline: $command->hideOthersTipsBeforeDeadline,
            now: $now,
        );
    }
}
