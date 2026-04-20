<?php

declare(strict_types=1);

namespace App\Command\RescheduleSportMatch;

use App\Repository\SportMatchRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RescheduleSportMatchHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RescheduleSportMatchCommand $command): void
    {
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);
        $sportMatch->reschedule(
            newKickoffAt: $command->newKickoffAt,
            now: \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }
}
