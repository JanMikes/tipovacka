<?php

declare(strict_types=1);

namespace App\Command\CreateGroup;

use App\Entity\Group;
use App\Entity\Membership;
use App\Repository\GroupRepository;
use App\Repository\MembershipRepository;
use App\Repository\TournamentRepository;
use App\Repository\UserRepository;
use App\Service\Group\PinGenerator;
use App\Service\Group\ShareableLinkTokenGenerator;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateGroupHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private MembershipRepository $membershipRepository,
        private TournamentRepository $tournamentRepository,
        private UserRepository $userRepository,
        private ProvideIdentity $identity,
        private PinGenerator $pinGenerator,
        private ShareableLinkTokenGenerator $linkTokenGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateGroupCommand $command): Group
    {
        $owner = $this->userRepository->get($command->ownerId);
        $tournament = $this->tournamentRepository->get($command->tournamentId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $group = new Group(
            id: $this->identity->next(),
            tournament: $tournament,
            owner: $owner,
            name: $command->name,
            description: $command->description,
            pin: $command->withPin ? $this->pinGenerator->generate() : null,
            shareableLinkToken: $this->linkTokenGenerator->generate(),
            createdAt: $now,
        );

        $this->groupRepository->save($group);

        $membership = new Membership(
            id: $this->identity->next(),
            group: $group,
            user: $owner,
            joinedAt: $now,
        );

        $this->membershipRepository->save($membership);

        return $group;
    }
}
