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
 * Who may look at a soutěž's žebříček.
 *
 * `leaderboard_view` deliberately has TWO doors (item 05, the standalone public
 * „Žebříček" page):
 *
 *  - **members / owner / admin** — any competition, private ones included;
 *  - **anyone at all, signed in or not** — but ONLY a `isGlobal` competition,
 *    i.e. one an admin curated and published for everybody to join. Those are
 *    already advertised publicly on `/souteze` with their player counts, and the
 *    board carries points and ranks only.
 *
 * A **private** competition therefore stays unreachable by guessing its UUID,
 * which is exactly what this voter is here to guarantee.
 *
 * `leaderboard_details` is the NARROW attribute the widening did not touch:
 * everything that goes past points and ranks — the tip matrix and a member's
 * per-match breakdown — still requires membership (or admin), because those
 * surfaces show tips and are governed by `TipVisibilityGate` /
 * `CompetitionEntitlements`. Widening `leaderboard_view` must never widen them.
 *
 * @extends Voter<'leaderboard_view'|'leaderboard_details'|'leaderboard_resolve_ties', Competition>
 */
final class LeaderboardVoter extends Voter
{
    public const string VIEW = 'leaderboard_view';
    public const string DETAILS = 'leaderboard_details';
    public const string RESOLVE_TIES = 'leaderboard_resolve_ties';

    public function __construct(
        private readonly MembershipRepository $membershipRepository,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::DETAILS, self::RESOLVE_TIES], true)
            && $subject instanceof Competition;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var Competition $subject */
        $user = $token->getUser();

        if (!$user instanceof User) {
            // Anonymous: the public board of a global competition, nothing else.
            // Every other attribute stays closed.
            return self::VIEW === $attribute && $subject->isGlobal;
        }

        $isAdmin = in_array(UserRole::ADMIN->value, $user->getRoles(), true);
        $isOwner = $user->id->equals($subject->owner->id);
        $isMember = $isOwner || $this->membershipRepository->hasActiveMembership($user->id, $subject->id);

        return match ($attribute) {
            self::VIEW => $isAdmin || $isMember || $subject->isGlobal,
            self::DETAILS => $isAdmin || $isMember,
            self::RESOLVE_TIES => ($isAdmin || $isOwner) && $subject->matchSource->isCompleted,
        };
    }
}
