<?php

declare(strict_types=1);

namespace App\Query\GetInvitationByToken;

use App\Repository\GroupInvitationRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetInvitationByTokenQuery
{
    public function __construct(
        private GroupInvitationRepository $invitationRepository,
    ) {
    }

    public function __invoke(GetInvitationByToken $query): InvitationLandingResult
    {
        $invitation = $this->invitationRepository->getByToken($query->token);

        return new InvitationLandingResult(
            token: $invitation->token,
            groupName: $invitation->group->name,
            tournamentName: $invitation->group->tournament->name,
            inviterNickname: $invitation->inviter->displayName,
            isExpired: $invitation->isExpiredAt($query->now),
            isAccepted: $invitation->isAccepted,
            isRevoked: $invitation->isRevoked,
            expiresAt: $invitation->expiresAt,
        );
    }
}
