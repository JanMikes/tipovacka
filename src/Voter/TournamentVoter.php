<?php

declare(strict_types=1);

namespace App\Voter;

use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\MembershipRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Tournament authorization voter.
 *
 * Stage 3 retrofit: VIEW on private tournaments is also granted to users with
 * an active Membership in any Group of the tournament.
 *
 * @extends Voter<'tournament_view'|'tournament_edit'|'tournament_delete'|'tournament_finish'|'tournament_create_match', Tournament>
 */
final class TournamentVoter extends Voter
{
    public const string VIEW = 'tournament_view';
    public const string EDIT = 'tournament_edit';
    public const string DELETE = 'tournament_delete';
    public const string FINISH = 'tournament_finish';
    public const string CREATE_MATCH = 'tournament_create_match';

    public function __construct(
        private readonly MembershipRepository $membershipRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::FINISH, self::CREATE_MATCH], true)
            && $subject instanceof Tournament;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var Tournament $subject */
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
                || $this->membershipRepository->hasActiveMembershipInTournament($currentUser->id, $subject->id),
            self::EDIT, self::DELETE, self::FINISH, self::CREATE_MATCH => $isAdmin || ($isOwner && $subject->isActive),
        };
    }
}
