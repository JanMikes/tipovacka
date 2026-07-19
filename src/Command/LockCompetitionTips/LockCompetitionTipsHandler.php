<?php

declare(strict_types=1);

namespace App\Command\LockCompetitionTips;

use App\Repository\CompetitionRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class LockCompetitionTipsHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(LockCompetitionTipsCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $competition->lockTips(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
