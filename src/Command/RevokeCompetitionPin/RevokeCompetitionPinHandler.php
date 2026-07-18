<?php

declare(strict_types=1);

namespace App\Command\RevokeCompetitionPin;

use App\Repository\CompetitionRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevokeCompetitionPinHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokeCompetitionPinCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $competition->revokePin(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
