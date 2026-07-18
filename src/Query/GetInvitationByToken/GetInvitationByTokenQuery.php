<?php

declare(strict_types=1);

namespace App\Query\GetInvitationByToken;

use App\Repository\CompetitionInvitationRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetInvitationByTokenQuery
{
    public function __construct(
        private CompetitionInvitationRepository $invitationRepository,
    ) {
    }

    public function __invoke(GetInvitationByToken $query): InvitationLandingResult
    {
        $invitation = $this->invitationRepository->getByToken($query->token);

        return new InvitationLandingResult(
            token: $invitation->token,
            competitionName: $invitation->competition->name,
            matchSourceName: $invitation->competition->matchSource->name,
            inviterNickname: $invitation->inviter->displayName,
            isExpired: $invitation->isExpiredAt($query->now),
            isAccepted: $invitation->isAccepted,
            isRevoked: $invitation->isRevoked,
            expiresAt: $invitation->expiresAt,
        );
    }
}
