<?php

declare(strict_types=1);

namespace App\Command\UpdateGuessOnBehalf;

use App\Entity\Guess;
use App\Enum\UserRole;
use App\Exception\GuessDeadlinePassed;
use App\Exception\GuessNotFound;
use App\Exception\InvalidGuessScore;
use App\Exception\MatchNotInCompetition;
use App\Repository\GuessRepository;
use App\Repository\UserRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\EffectiveTipDeadlineResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsMessageHandler]
final readonly class UpdateGuessOnBehalfHandler
{
    public function __construct(
        private GuessRepository $guessRepository,
        private UserRepository $userRepository,
        private CompetitionMatchProvider $matchProvider,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateGuessOnBehalfCommand $command): Guess
    {
        if ($command->homeScore < 0 || $command->awayScore < 0) {
            throw InvalidGuessScore::create();
        }

        $guess = $this->guessRepository->get($command->guessId);
        $actingUser = $this->userRepository->get($command->actingUserId);

        $isAdmin = in_array(UserRole::ADMIN->value, $actingUser->getRoles(), true);

        if (!$isAdmin && !$actingUser->id->equals($guess->competition->owner->id)) {
            throw new AccessDeniedException('Only the competition owner or an admin can edit a member\'s guess.');
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
