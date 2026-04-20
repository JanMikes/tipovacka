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
 * @extends Voter<'leaderboard_view'|'leaderboard_resolve_ties', Group>
 */
final class LeaderboardVoter extends Voter
{
    public const string VIEW = 'leaderboard_view';
    public const string RESOLVE_TIES = 'leaderboard_resolve_ties';

    public function __construct(
        private readonly MembershipRepository $membershipRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::RESOLVE_TIES], true)
            && $subject instanceof Group;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var Group $subject */
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $isAdmin = in_array(UserRole::ADMIN->value, $user->getRoles(), true);
        $isOwner = $user->id->equals($subject->owner->id);
        $isMember = $isOwner || $this->membershipRepository->hasActiveMembership($user->id, $subject->id);

        return match ($attribute) {
            self::VIEW => $isAdmin || $isMember,
            self::RESOLVE_TIES => ($isAdmin || $isOwner) && $subject->tournament->isFinished,
        };
    }
}
