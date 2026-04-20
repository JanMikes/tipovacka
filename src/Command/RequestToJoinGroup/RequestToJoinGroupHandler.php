<?php

declare(strict_types=1);

namespace App\Command\RequestToJoinGroup;

use App\Entity\GroupJoinRequest;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedTournament;
use App\Exception\DuplicatePendingJoinRequest;
use App\Exception\JoinRequestNotAllowed;
use App\Repository\GroupJoinRequestRepository;
use App\Repository\GroupRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RequestToJoinGroupHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private UserRepository $userRepository,
        private MembershipRepository $membershipRepository,
        private GroupJoinRequestRepository $joinRequestRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RequestToJoinGroupCommand $command): GroupJoinRequest
    {
        $group = $this->groupRepository->get($command->groupId);
        $user = $this->userRepository->get($command->userId);

        if (!$group->tournament->isPublic) {
            throw JoinRequestNotAllowed::privateTournament();
        }

        if ($group->tournament->isFinished) {
            throw CannotJoinFinishedTournament::forGroup($group->id);
        }

        if ($this->membershipRepository->hasActiveMembership($user->id, $group->id)) {
            throw AlreadyMember::in($group->id);
        }

        if ($this->joinRequestRepository->hasPendingFor($user->id, $group->id)) {
            throw DuplicatePendingJoinRequest::forGroup($group->id);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $request = new GroupJoinRequest(
            id: $this->identity->next(),
            group: $group,
            user: $user,
            requestedAt: $now,
        );

        $this->joinRequestRepository->save($request);

        return $request;
    }
}
