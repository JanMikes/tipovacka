<?php

declare(strict_types=1);

namespace App\Command\ReopenMatchSource;

use App\Repository\MatchSourceRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReopenMatchSourceHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ReopenMatchSourceCommand $command): void
    {
        $matchSource = $this->matchSourceRepository->get($command->matchSourceId);
        $matchSource->reopen(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
