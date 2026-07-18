<?php

declare(strict_types=1);

namespace App\Command\SoftDeleteMatchSource;

use App\Repository\MatchSourceRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SoftDeleteMatchSourceHandler
{
    public function __construct(
        private MatchSourceRepository $matchSourceRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SoftDeleteMatchSourceCommand $command): void
    {
        $matchSource = $this->matchSourceRepository->get($command->matchSourceId);
        $matchSource->softDelete(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
