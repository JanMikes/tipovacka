<?php

declare(strict_types=1);

namespace App\Command\SoftDeleteSportMatch;

use App\Repository\SportMatchRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SoftDeleteSportMatchHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SoftDeleteSportMatchCommand $command): void
    {
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);
        $sportMatch->softDelete(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
