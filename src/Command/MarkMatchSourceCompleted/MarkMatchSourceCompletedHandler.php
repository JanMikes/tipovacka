<?php

declare(strict_types=1);

namespace App\Command\MarkMatchSourceCompleted;

use App\Repository\MatchSourceRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MarkMatchSourceCompletedHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(MarkMatchSourceCompletedCommand $command): void
    {
        $matchSource = $this->matchSourceRepository->get($command->matchSourceId);
        $matchSource->markCompleted(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
