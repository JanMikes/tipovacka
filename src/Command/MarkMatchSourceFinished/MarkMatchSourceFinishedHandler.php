<?php

declare(strict_types=1);

namespace App\Command\MarkMatchSourceFinished;

use App\Repository\MatchSourceRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MarkMatchSourceFinishedHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(MarkMatchSourceFinishedCommand $command): void
    {
        $matchSource = $this->matchSourceRepository->get($command->matchSourceId);
        $matchSource->markFinished(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
