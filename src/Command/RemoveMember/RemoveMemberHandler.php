<?php

declare(strict_types=1);

namespace App\Command\RemoveMember;

use App\Exception\CannotLeaveAsOwner;
use App\Exception\NotAMember;
use App\Repository\GroupRepository;
use App\Repository\MembershipRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RemoveMemberHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private MembershipRepository $membershipRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RemoveMemberCommand $command): void
    {
        $group = $this->groupRepository->get($command->groupId);

        if ($group->owner->id->equals($command->targetUserId)) {
            throw CannotLeaveAsOwner::of($group->id);
        }

        $membership = $this->membershipRepository->findActiveMembership($command->targetUserId, $group->id);

        if (null === $membership) {
            throw NotAMember::of($group->id);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        // Capture the "is this their last membership?" state before we mark it
        // inactive — findMyActive runs a DB query and won't see the uncommitted leftAt.
        $user = $membership->user;
        $activeMemberships = $this->membershipRepository->findMyActive($user->id);
        $isLastMembership = 1 === count($activeMemberships)
            && $activeMemberships[0]->id->equals($membership->id);

        $membership->removeBy(
            removedByUserId: $command->ownerId,
            now: $now,
        );

        // An anonymous user only exists to participate in the group(s) their manager
        // added them to. Once they're no longer a member anywhere, tidy up the User
        // record so they don't linger as unreachable orphans.
        if ($user->isAnonymous && $isLastMembership) {
            $user->softDelete($now);
        }
    }
}
