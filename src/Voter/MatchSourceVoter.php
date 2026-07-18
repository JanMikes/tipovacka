<?php

declare(strict_types=1);

namespace App\Voter;

use App\Entity\MatchSource;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\MembershipRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<'match_source_view'|'match_source_edit'|'match_source_delete'|'match_source_finish'|'match_source_create_match'|'match_source_create_competition', MatchSource>
 */
final class MatchSourceVoter extends Voter
{
    public const string VIEW = 'match_source_view';
    public const string EDIT = 'match_source_edit';
    public const string DELETE = 'match_source_delete';
    public const string FINISH = 'match_source_finish';
    public const string CREATE_MATCH = 'match_source_create_match';
    public const string CREATE_COMPETITION = 'match_source_create_competition';

    public function __construct(
        private readonly MembershipRepository $membershipRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::FINISH, self::CREATE_MATCH, self::CREATE_COMPETITION], true)
            && $subject instanceof MatchSource;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var MatchSource $subject */
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return self::VIEW === $attribute && $subject->isPublic;
        }

        $isAdmin = in_array(UserRole::ADMIN->value, $currentUser->getRoles(), true);
        $isOwner = $currentUser->id->equals($subject->owner->id);

        return match ($attribute) {
            self::VIEW => $subject->isPublic
                || $isAdmin
                || $isOwner
                || $this->membershipRepository->hasActiveMembershipInMatchSource($currentUser->id, $subject->id),
            self::EDIT, self::DELETE, self::FINISH, self::CREATE_MATCH => $isAdmin || ($isOwner && $subject->isActive),
            self::CREATE_COMPETITION => $currentUser->isVerified
                && $subject->isActive
                && (
                    $isAdmin
                    || $isOwner
                    || $subject->isPublic
                    || $this->membershipRepository->hasActiveMembershipInMatchSource($currentUser->id, $subject->id)
                ),
        };
    }
}
