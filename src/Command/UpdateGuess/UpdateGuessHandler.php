<?php

declare(strict_types=1);

namespace App\Command\UpdateGuess;

use App\Entity\Guess;
use App\Exception\GuessDeadlinePassed;
use App\Exception\GuessNotFound;
use App\Exception\InvalidGuessScore;
use App\Exception\MatchNotInCompetition;
use App\Repository\GuessRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateGuessHandler
{
    public function __construct(
        private GuessRepository $guessRepository,
        private CompetitionMatchProvider $matchProvider,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateGuessCommand $command): Guess
    {
        if ($command->homeScore < 0 || $command->awayScore < 0) {
            throw InvalidGuessScore::create();
        }

        $guess = $this->guessRepository->get($command->guessId);

        if (!$command->userId->equals($guess->user->id)) {
            throw GuessNotFound::withId($command->guessId);
        }

        if (null !== $guess->deletedAt) {
            throw GuessNotFound::withId($command->guessId);
        }

        if (!$this->matchProvider->includes($guess->competition, $guess->sportMatch)) {
            throw MatchNotInCompetition::create();
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $deadline = $this->deadlineResolver->resolve($guess->competition, $guess->sportMatch);

        if (!$guess->sportMatch->isOpenForGuesses || $now >= $deadline) {
            throw GuessDeadlinePassed::create();
        }

        $guess->updateScores($command->homeScore, $command->awayScore, $now);

        return $guess;
    }
}
