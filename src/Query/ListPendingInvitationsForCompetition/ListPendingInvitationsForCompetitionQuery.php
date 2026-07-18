<?php

declare(strict_types=1);

namespace App\Query\ListPendingInvitationsForCompetition;

use App\Entity\CompetitionInvitation;
use App\Repository\CompetitionInvitationRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListPendingInvitationsForCompetitionQuery
{
    public function __construct(
        private CompetitionInvitationRepository $invitationRepository,
    ) {
    }

    /**
     * @return list<PendingInvitationListItem>
     */
    public function __invoke(ListPendingInvitationsForCompetition $query): array
    {
        $invitations = $this->invitationRepository->findPendingByCompetition($query->competitionId, $query->now);

        return array_map(
            static fn (CompetitionInvitation $i): PendingInvitationListItem => new PendingInvitationListItem(
                invitationId: $i->id,
                email: $i->email,
                inviterNickname: $i->inviter->displayName,
                sentAt: $i->createdAt,
                expiresAt: $i->expiresAt,
            ),
            $invitations,
        );
    }
}
