<?php

declare(strict_types=1);

namespace App\Command\UpdateGuessOnBehalf;

use App\Entity\Guess;
use App\Enum\UserRole;
use App\Exception\GuessDeadlinePassed;
use App\Exception\GuessNotFound;
use App\Exception\InvalidGuessScore;
use App\Repository\GuessRepository;
use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsMessageHandler]
final readonly class UpdateGuessOnBehalfHandler
{
    public function __construct(
        private GuessRepository $guessRepository,
        private UserRepository $userRepository,
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

        if (!$isAdmin && !$actingUser->id->equals($guess->group->owner->id)) {
            throw new AccessDeniedException('Only the group owner or an admin can edit a member\'s guess.');
        }

        if (null !== $guess->deletedAt) {
            throw GuessNotFound::withId($command->guessId);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        if (!$guess->sportMatch->isOpenForGuesses || $now >= $guess->sportMatch->kickoffAt) {
            throw GuessDeadlinePassed::create();
        }

        $guess->updateScores($command->homeScore, $command->awayScore, $now);

        return $guess;
    }
}
