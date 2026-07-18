<?php

declare(strict_types=1);

namespace App\Command\RevokeCompetitionInvitation;

use App\Exception\NotAMember;
use App\Repository\CompetitionInvitationRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevokeCompetitionInvitationHandler
{
    public function __construct(
        private CompetitionInvitationRepository $invitationRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokeCompetitionInvitationCommand $command): void
    {
        $invitation = $this->invitationRepository->get($command->invitationId);

        $isInviter = $command->revokerId->equals($invitation->inviter->id);
        $isOwner = $command->revokerId->equals($invitation->competition->owner->id);

        if (!$isInviter && !$isOwner) {
            throw NotAMember::of($invitation->competition->id);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $invitation->revoke($now);
    }
}
