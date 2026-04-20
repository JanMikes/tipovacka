<?php

declare(strict_types=1);

namespace App\Command\PostponeSportMatch;

use App\Repository\SportMatchRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PostponeSportMatchHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PostponeSportMatchCommand $command): void
    {
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);
        $sportMatch->postponeTo(
            newKickoffAt: $command->newKickoffAt,
            now: \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }
}
