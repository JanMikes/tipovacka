<?php

declare(strict_types=1);

namespace App\Voter;

use App\Entity\Group;
use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\GroupRepository;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use App\Service\EffectiveTipDeadlineResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Authorization voter for Guess actions.
 *
 * SUBMIT and VIEW require a GuessVotingContext subject (SportMatch + groupId),
 * because a "submit" decision is specific to the group under which the user
 * is tipping. UPDATE takes an existing Guess and checks ownership + match state.
 * SUBMIT_ON_BEHALF / UPDATE_ON_BEHALF let a group owner (or admin) fill or edit
 * a guess for another active member (e.g. a proxy player who never logs in).
 *
 * @extends Voter<'guess_submit'|'guess_update'|'guess_view'|'guess_submit_on_behalf'|'guess_update_on_behalf', Guess|GuessVotingContext|GuessOnBehalfContext>
 */
final class GuessVoter extends Voter
{
    public const string SUBMIT = 'guess_submit';
    public const string UPDATE = 'guess_update';
    public const string VIEW = 'guess_view';
    public const string SUBMIT_ON_BEHALF = 'guess_submit_on_behalf';
    public const string UPDATE_ON_BEHALF = 'guess_update_on_behalf';

    public function __construct(
        private readonly MembershipRepository $membershipRepository,
        private readonly GuessRepository $guessRepository,
        private readonly GroupRepository $groupRepository,
        private readonly EffectiveTipDeadlineResolver $deadlineResolver,
        private readonly ClockInterface $clock,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::UPDATE, self::UPDATE_ON_BEHALF => $subject instanceof Guess,
            self::SUBMIT_ON_BEHALF => $subject instanceof GuessOnBehalfContext,
            self::SUBMIT, self::VIEW => $subject instanceof GuessVotingContext,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        if (self::UPDATE === $attribute) {
            \assert($subject instanceof Guess);

            if (!$currentUser->id->equals($subject->user->id)) {
                return false;
            }

            if (!$this->isWithinDeadline($subject->group, $subject->sportMatch)) {
                return false;
            }

            return null === $subject->deletedAt;
        }

        if (self::UPDATE_ON_BEHALF === $attribute) {
            \assert($subject instanceof Guess);

            if (!$this->isGroupManager($currentUser, $subject->group->owner->id)) {
                return false;
            }

            if (!$this->isWithinDeadline($subject->group, $subject->sportMatch)) {
                return false;
            }

            return null === $subject->deletedAt;
        }

        if (self::SUBMIT_ON_BEHALF === $attribute) {
            \assert($subject instanceof GuessOnBehalfContext);

            if (!$this->isGroupManager($currentUser, $subject->group->owner->id)) {
                return false;
            }

            if (!$this->membershipRepository->hasActiveMembership($subject->targetUser->id, $subject->group->id)) {
                return false;
            }

            if (!$subject->sportMatch->tournament->id->equals($subject->group->tournament->id)) {
                return false;
            }

            if (!$this->isWithinDeadline($subject->group, $subject->sportMatch)) {
                return false;
            }

            $existing = $this->guessRepository->findActiveByUserMatchGroup(
                $subject->targetUser->id,
                $subject->sportMatch->id,
                $subject->group->id,
            );

            return null === $existing;
        }

        \assert($subject instanceof GuessVotingContext);

        $isMember = $this->membershipRepository->hasActiveMembership($currentUser->id, $subject->groupId);

        if (!$isMember) {
            return false;
        }

        if (self::VIEW === $attribute) {
            return true;
        }

        // SUBMIT: the match must belong to the same tournament as the group,
        // must still be open for guesses, must be before the effective deadline
        // (per-match override → group default → kickoff), and the user must not
        // already have an active guess for this (user, match, group) triple.
        $sportMatch = $subject->sportMatch;
        $group = $this->groupRepository->get($subject->groupId);

        if (!$this->isWithinDeadline($group, $sportMatch)) {
            return false;
        }

        $existing = $this->guessRepository->findActiveByUserMatchGroup(
            $currentUser->id,
            $sportMatch->id,
            $subject->groupId,
        );

        return null === $existing;
    }

    private function isWithinDeadline(Group $group, SportMatch $sportMatch): bool
    {
        if (!$sportMatch->isOpenForGuesses) {
            return false;
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $deadline = $this->deadlineResolver->resolve($group, $sportMatch);

        return $now < $deadline;
    }

    private function isGroupManager(User $currentUser, \Symfony\Component\Uid\Uuid $ownerId): bool
    {
        if (in_array(UserRole::ADMIN->value, $currentUser->getRoles(), true)) {
            return true;
        }

        return $currentUser->id->equals($ownerId);
    }
}
