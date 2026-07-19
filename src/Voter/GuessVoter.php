<?php

declare(strict_types=1);

namespace App\Voter;

use App\Entity\Competition;
use App\Entity\Guess;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\CompetitionRepository;
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
 * SUBMIT and VIEW require a GuessVotingContext subject (SportMatch + competitionId),
 * because a "submit" decision is specific to the competition under which the user
 * is tipping. UPDATE takes an existing Guess and checks ownership + match state.
 * SUBMIT_ON_BEHALF / UPDATE_ON_BEHALF let a competition owner (or admin) fill or edit
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
        private readonly CompetitionRepository $competitionRepository,
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

            if (!$this->isWithinDeadline($subject->competition, $subject->sportMatch, $subject->user)) {
                return false;
            }

            return null === $subject->deletedAt;
        }

        if (self::UPDATE_ON_BEHALF === $attribute) {
            \assert($subject instanceof Guess);

            // On-behalf tipping is disabled for global competitions — each player owns their tips.
            if ($subject->competition->isGlobal) {
                return false;
            }

            if (!$this->isCompetitionManager($currentUser, $subject->competition->owner->id)) {
                return false;
            }

            // Entitlements follow the guess owner — it is their tip window.
            if (!$this->isWithinDeadline($subject->competition, $subject->sportMatch, $subject->user)) {
                return false;
            }

            return null === $subject->deletedAt;
        }

        if (self::SUBMIT_ON_BEHALF === $attribute) {
            \assert($subject instanceof GuessOnBehalfContext);

            // On-behalf tipping is disabled for global competitions — each player owns their tips.
            if ($subject->competition->isGlobal) {
                return false;
            }

            if (!$this->isCompetitionManager($currentUser, $subject->competition->owner->id)) {
                return false;
            }

            if (!$this->membershipRepository->hasActiveMembership($subject->targetUser->id, $subject->competition->id)) {
                return false;
            }

            if (!$subject->sportMatch->matchSource->id->equals($subject->competition->matchSource->id)) {
                return false;
            }

            if (!$this->isWithinDeadline($subject->competition, $subject->sportMatch, $subject->targetUser)) {
                return false;
            }

            $existing = $this->guessRepository->findActiveByUserMatchCompetition(
                $subject->targetUser->id,
                $subject->sportMatch->id,
                $subject->competition->id,
            );

            return null === $existing;
        }

        \assert($subject instanceof GuessVotingContext);

        $isMember = $this->membershipRepository->hasActiveMembership($currentUser->id, $subject->competitionId);

        if (!$isMember) {
            return false;
        }

        if (self::VIEW === $attribute) {
            return true;
        }

        // SUBMIT: the match must belong to the same match source as the competition,
        // must still be open for guesses, must be before the effective deadline
        // (EffectiveTipDeadlineResolver — the single deadline authority), and the user
        // must not already have an active guess for this (user, match, competition) triple.
        $sportMatch = $subject->sportMatch;
        $competition = $this->competitionRepository->get($subject->competitionId);

        if (!$this->isWithinDeadline($competition, $sportMatch, $currentUser)) {
            return false;
        }

        $existing = $this->guessRepository->findActiveByUserMatchCompetition(
            $currentUser->id,
            $sportMatch->id,
            $subject->competitionId,
        );

        return null === $existing;
    }

    private function isWithinDeadline(Competition $competition, SportMatch $sportMatch, User $user): bool
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        return !$this->deadlineResolver->isLocked($competition, $sportMatch, $user, $now);
    }

    private function isCompetitionManager(User $currentUser, \Symfony\Component\Uid\Uuid $ownerId): bool
    {
        if (in_array(UserRole::ADMIN->value, $currentUser->getRoles(), true)) {
            return true;
        }

        return $currentUser->id->equals($ownerId);
    }
}
