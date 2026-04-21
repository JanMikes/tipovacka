<?php

declare(strict_types=1);

namespace App\Command\DeleteGuess;

use App\Exception\GuessDeadlinePassed;
use App\Exception\GuessNotFound;
use App\Repository\GuessRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteGuessHandler
{
    public function __construct(
        private GuessRepository $guessRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(DeleteGuessCommand $command): void
    {
        $guess = $this->guessRepository->get($command->guessId);

        if (!$command->userId->equals($guess->user->id)) {
            throw GuessNotFound::withId($command->guessId);
        }

        if (null !== $guess->deletedAt) {
            throw GuessNotFound::withId($command->guessId);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        if (!$guess->sportMatch->isOpenForGuesses || $now >= $guess->sportMatch->kickoffAt) {
            throw GuessDeadlinePassed::create();
        }

        $guess->voidGuess($now);
    }
}
