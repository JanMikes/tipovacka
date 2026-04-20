<?php

declare(strict_types=1);

namespace App\Command\RevokeGroupInvitation;

use App\Exception\NotAMember;
use App\Repository\GroupInvitationRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevokeGroupInvitationHandler
{
    public function __construct(
        private GroupInvitationRepository $invitationRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokeGroupInvitationCommand $command): void
    {
        $invitation = $this->invitationRepository->get($command->invitationId);

        $isInviter = $command->revokerId->equals($invitation->inviter->id);
        $isOwner = $command->revokerId->equals($invitation->group->owner->id);

        if (!$isInviter && !$isOwner) {
            throw NotAMember::of($invitation->group->id);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $invitation->revoke($now);
    }
}
