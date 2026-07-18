<?php

declare(strict_types=1);

namespace App\Voter;

use App\Entity\Competition;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\MembershipRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<'competition_view'|'competition_edit'|'competition_delete'|'competition_manage_members'|'competition_join'|'competition_leave'|'competition_invite_member'|'competition_request_join', Competition>
 */
final class CompetitionVoter extends Voter
{
    public const string VIEW = 'competition_view';
    public const string EDIT = 'competition_edit';
    public const string DELETE = 'competition_delete';
    public const string MANAGE_MEMBERS = 'competition_manage_members';
    public const string JOIN = 'competition_join';
    public const string LEAVE = 'competition_leave';
    public const string INVITE_MEMBER = 'competition_invite_member';
    public const string REQUEST_JOIN = 'competition_request_join';

    public function __construct(
        private readonly MembershipRepository $membershipRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::MANAGE_MEMBERS,
            self::JOIN,
            self::LEAVE,
            self::INVITE_MEMBER,
            self::REQUEST_JOIN,
        ], true) && $subject instanceof Competition;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var Competition $subject */
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        $isAdmin = in_array(UserRole::ADMIN->value, $currentUser->getRoles(), true);
        $isOwner = $currentUser->id->equals($subject->owner->id);
        $isMember = $isOwner || $this->membershipRepository->hasActiveMembership($currentUser->id, $subject->id);

        return match ($attribute) {
            self::VIEW => $isAdmin || $isMember,
            self::EDIT => ($isAdmin || $isOwner) && $subject->isNotDeleted && !$subject->matchSource->isFinished,
            self::DELETE => $isAdmin || $isOwner,
            self::MANAGE_MEMBERS => $isAdmin || $isOwner,
            self::JOIN => $currentUser->isVerified && !$subject->matchSource->isFinished && $subject->isNotDeleted,
            self::LEAVE => $isMember && !$isOwner,
            self::INVITE_MEMBER => ($isAdmin || $isOwner) && $subject->isNotDeleted && !$subject->matchSource->isFinished,
            self::REQUEST_JOIN => $subject->matchSource->isPublic
                && $subject->isNotDeleted
                && !$subject->matchSource->isFinished
                && $currentUser->isVerified
                && !$isMember,
        };
    }
}
