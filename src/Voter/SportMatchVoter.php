<?php

declare(strict_types=1);

namespace App\Voter;

use App\Entity\SportMatch;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * SportMatch authorization voter.
 *
 * - CREATE takes a Tournament subject (to authorize adding a match to it).
 * - Other attributes take a SportMatch subject and delegate ownership checks via its tournament.
 * - VIEW delegates to TournamentVoter::VIEW on the underlying tournament.
 *
 * @extends Voter<'sport_match_view'|'sport_match_create'|'sport_match_edit'|'sport_match_set_score'|'sport_match_cancel'|'sport_match_delete', SportMatch|Tournament>
 */
final class SportMatchVoter extends Voter
{
    public const string VIEW = 'sport_match_view';
    public const string CREATE = 'sport_match_create';
    public const string EDIT = 'sport_match_edit';
    public const string SET_SCORE = 'sport_match_set_score';
    public const string CANCEL = 'sport_match_cancel';
    public const string DELETE = 'sport_match_delete';

    public function __construct(
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::SET_SCORE, self::CANCEL, self::DELETE], true)) {
            return false;
        }

        if (self::CREATE === $attribute) {
            return $subject instanceof Tournament;
        }

        return $subject instanceof SportMatch;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $currentUser = $token->getUser();

        if (self::VIEW === $attribute) {
            \assert($subject instanceof SportMatch);

            return $this->security->isGranted(TournamentVoter::VIEW, $subject->tournament);
        }

        if (!$currentUser instanceof User) {
            return false;
        }

        $isAdmin = in_array(UserRole::ADMIN->value, $currentUser->getRoles(), true);

        if (self::CREATE === $attribute) {
            \assert($subject instanceof Tournament);

            if (!$subject->isActive) {
                return $isAdmin;
            }

            $isOwner = $currentUser->id->equals($subject->owner->id);

            return $isAdmin || $isOwner;
        }

        \assert($subject instanceof SportMatch);
        $tournament = $subject->tournament;
        $isOwner = $currentUser->id->equals($tournament->owner->id);

        if (self::EDIT === $attribute) {
            if ($subject->isCancelled || null !== $subject->deletedAt) {
                return false;
            }

            return $isAdmin || ($isOwner && $tournament->isActive);
        }

        return $isAdmin || ($isOwner && $tournament->isActive);
    }
}
