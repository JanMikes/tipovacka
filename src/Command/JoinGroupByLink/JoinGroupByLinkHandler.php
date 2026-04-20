<?php

declare(strict_types=1);

namespace App\Command\JoinGroupByLink;

use App\Entity\Group;
use App\Entity\Membership;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedTournament;
use App\Repository\GroupRepository;
use App\Repository\MembershipRepository;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class JoinGroupByLinkHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private MembershipRepository $membershipRepository,
        private UserRepository $userRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(JoinGroupByLinkCommand $command): Group
    {
        $group = $this->groupRepository->getByShareableLinkToken($command->token);
        $user = $this->userRepository->get($command->userId);

        if ($group->tournament->isFinished) {
            throw CannotJoinFinishedTournament::forGroup($group->id);
        }

        if ($this->membershipRepository->hasActiveMembership($user->id, $group->id)) {
            throw AlreadyMember::in($group->id);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $membership = new Membership(
            id: $this->identity->next(),
            group: $group,
            user: $user,
            joinedAt: $now,
        );

        $this->membershipRepository->save($membership);

        return $group;
    }
}
