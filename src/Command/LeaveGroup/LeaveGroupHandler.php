<?php

declare(strict_types=1);

namespace App\Command\LeaveGroup;

use App\Exception\CannotLeaveAsOwner;
use App\Exception\NotAMember;
use App\Repository\GroupRepository;
use App\Repository\MembershipRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class LeaveGroupHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private MembershipRepository $membershipRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(LeaveGroupCommand $command): void
    {
        $group = $this->groupRepository->get($command->groupId);

        if ($group->owner->id->equals($command->userId)) {
            throw CannotLeaveAsOwner::of($group->id);
        }

        $membership = $this->membershipRepository->findActiveMembership($command->userId, $group->id);

        if (null === $membership) {
            throw NotAMember::of($group->id);
        }

        $membership->leave(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
