<?php

declare(strict_types=1);

namespace App\Voter;

use App\Entity\Group;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\MembershipRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<'group_view'|'group_edit'|'group_delete'|'group_manage_members'|'group_join'|'group_leave'|'group_invite_member'|'group_request_join', Group>
 */
final class GroupVoter extends Voter
{
    public const string VIEW = 'group_view';
    public const string EDIT = 'group_edit';
    public const string DELETE = 'group_delete';
    public const string MANAGE_MEMBERS = 'group_manage_members';
    public const string JOIN = 'group_join';
    public const string LEAVE = 'group_leave';
    public const string INVITE_MEMBER = 'group_invite_member';
    public const string REQUEST_JOIN = 'group_request_join';

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
        ], true) && $subject instanceof Group;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var Group $subject */
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        $isAdmin = in_array(UserRole::ADMIN->value, $currentUser->getRoles(), true);
        $isOwner = $currentUser->id->equals($subject->owner->id);
        $isMember = $isOwner || $this->membershipRepository->hasActiveMembership($currentUser->id, $subject->id);

        return match ($attribute) {
            self::VIEW => $isAdmin || $isMember,
            self::EDIT => ($isAdmin || $isOwner) && $subject->isNotDeleted && !$subject->tournament->isFinished,
            self::DELETE => $isAdmin || $isOwner,
            self::MANAGE_MEMBERS => $isAdmin || $isOwner,
            self::JOIN => $currentUser->isVerified && !$subject->tournament->isFinished && $subject->isNotDeleted,
            self::LEAVE => $isMember && !$isOwner,
            self::INVITE_MEMBER => $isMember && $subject->isNotDeleted && !$subject->tournament->isFinished,
            self::REQUEST_JOIN => $subject->tournament->isPublic
                && $subject->isNotDeleted
                && !$subject->tournament->isFinished
                && $currentUser->isVerified
                && !$isMember,
        };
    }
}
