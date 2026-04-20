<?php

declare(strict_types=1);

namespace App\Command\SendGroupInvitation;

use App\Entity\GroupInvitation;
use App\Repository\GroupInvitationRepository;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Service\Group\InvitationTokenGenerator;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendGroupInvitationHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private UserRepository $userRepository,
        private GroupInvitationRepository $invitationRepository,
        private InvitationTokenGenerator $tokenGenerator,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SendGroupInvitationCommand $command): GroupInvitation
    {
        $group = $this->groupRepository->get($command->groupId);
        $inviter = $this->userRepository->get($command->inviterId);

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $expiresAt = $now->modify('+7 days');
        $normalisedEmail = strtolower(trim($command->email));

        $invitation = new GroupInvitation(
            id: $this->identity->next(),
            group: $group,
            inviter: $inviter,
            email: $normalisedEmail,
            token: $this->tokenGenerator->generate(),
            createdAt: $now,
            expiresAt: $expiresAt,
        );

        $this->invitationRepository->save($invitation);

        return $invitation;
    }
}
