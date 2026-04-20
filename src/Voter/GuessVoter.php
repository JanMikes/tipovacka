<?php

declare(strict_types=1);

namespace App\Voter;

use App\Entity\Guess;
use App\Entity\User;
use App\Repository\GuessRepository;
use App\Repository\MembershipRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Authorization voter for Guess actions.
 *
 * SUBMIT and VIEW require a GuessVotingContext subject (SportMatch + groupId),
 * because a "submit" decision is specific to the group under which the user
 * is tipping. UPDATE takes an existing Guess and checks ownership + match state.
 *
 * @extends Voter<'guess_submit'|'guess_update'|'guess_view', Guess|GuessVotingContext>
 */
final class GuessVoter extends Voter
{
    public const string SUBMIT = 'guess_submit';
    public const string UPDATE = 'guess_update';
    public const string VIEW = 'guess_view';

    public function __construct(
        private readonly MembershipRepository $membershipRepository,
        private readonly GuessRepository $guessRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::SUBMIT, self::UPDATE, self::VIEW], true)) {
            return false;
        }

        if (self::UPDATE === $attribute) {
            return $subject instanceof Guess;
        }

        return $subject instanceof GuessVotingContext;
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

            if (!$subject->sportMatch->isOpenForGuesses) {
                return false;
            }

            return null === $subject->deletedAt;
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
        // must still be open for guesses, and the user must not already have
        // an active guess for this (user, match, group) triple.
        $sportMatch = $subject->sportMatch;

        if (!$sportMatch->isOpenForGuesses) {
            return false;
        }

        $existing = $this->guessRepository->findActiveByUserMatchGroup(
            $currentUser->id,
            $sportMatch->id,
            $subject->groupId,
        );

        return null === $existing;
    }
}
