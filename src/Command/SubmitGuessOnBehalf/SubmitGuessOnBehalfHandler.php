<?php

declare(strict_types=1);

namespace App\Command\SubmitGuessOnBehalf;

use App\Entity\Guess;
use App\Enum\UserRole;
use App\Exception\GuessAlreadyExists;
use App\Exception\GuessDeadlinePassed;
use App\Exception\InvalidGuessScore;
use App\Exception\NotAMember;
use App\Repository\GroupRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Repository\UserRepository;
use App\Service\EffectiveTipDeadlineResolver;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsMessageHandler]
final readonly class SubmitGuessOnBehalfHandler
{
    public function __construct(
        private GuessRepository $guessRepository,
        private SportMatchRepository $sportMatchRepository,
        private GroupRepository $groupRepository,
        private UserRepository $userRepository,
        private MembershipRepository $membershipRepository,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SubmitGuessOnBehalfCommand $command): Guess
    {
        if ($command->homeScore < 0 || $command->awayScore < 0) {
            throw InvalidGuessScore::create();
        }

        $actingUser = $this->userRepository->get($command->actingUserId);
        $group = $this->groupRepository->get($command->groupId);

        $isAdmin = in_array(UserRole::ADMIN->value, $actingUser->getRoles(), true);

        if (!$isAdmin && !$actingUser->id->equals($group->owner->id)) {
            throw new AccessDeniedException('Only the group owner or an admin can tip on behalf of a member.');
        }

        $targetUser = $this->userRepository->get($command->targetUserId);
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);

        if (!$this->membershipRepository->hasActiveMembership($targetUser->id, $group->id)) {
            throw NotAMember::of($group->id);
        }

        if (!$sportMatch->tournament->id->equals($group->tournament->id)) {
            throw NotAMember::of($group->id);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $deadline = $this->deadlineResolver->resolve($group, $sportMatch);

        if (!$sportMatch->isOpenForGuesses || $now >= $deadline) {
            throw GuessDeadlinePassed::create();
        }

        $existing = $this->guessRepository->findActiveByUserMatchGroup(
            $targetUser->id,
            $sportMatch->id,
            $group->id,
        );

        if (null !== $existing) {
            throw GuessAlreadyExists::create();
        }

        $guess = new Guess(
            id: $this->identity->next(),
            user: $targetUser,
            sportMatch: $sportMatch,
            group: $group,
            homeScore: $command->homeScore,
            awayScore: $command->awayScore,
            submittedAt: $now,
            submittedBy: $actingUser,
        );

        $this->guessRepository->save($guess);

        return $guess;
    }
}
