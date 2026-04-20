<?php

declare(strict_types=1);

namespace App\Command\MarkSportMatchLive;

use App\Repository\SportMatchRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MarkSportMatchLiveHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(MarkSportMatchLiveCommand $command): void
    {
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);
        $sportMatch->beginLive(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
