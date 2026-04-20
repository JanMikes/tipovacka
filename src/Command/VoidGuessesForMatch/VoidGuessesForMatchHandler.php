<?php

declare(strict_types=1);

namespace App\Command\VoidGuessesForMatch;

use App\Repository\GuessRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class VoidGuessesForMatchHandler
{
    public function __construct(
        private GuessRepository $guessRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(VoidGuessesForMatchCommand $command): int
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        return $this->guessRepository->voidAllForMatch($command->sportMatchId, $now);
    }
}
