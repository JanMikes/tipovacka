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

        $membership->removeBy(
            removedByUserId: $command->ownerId,
            now: \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }
}
