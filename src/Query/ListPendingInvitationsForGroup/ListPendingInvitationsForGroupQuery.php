<?php

declare(strict_types=1);

namespace App\Query\ListPendingInvitationsForGroup;

use App\Entity\GroupInvitation;
use App\Repository\GroupInvitationRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListPendingInvitationsForGroupQuery
{
    public function __construct(
        private GroupInvitationRepository $invitationRepository,
    ) {
    }

    /**
     * @return list<PendingInvitationListItem>
     */
    public function __invoke(ListPendingInvitationsForGroup $query): array
    {
        $invitations = $this->invitationRepository->findPendingByGroup($query->groupId, $query->now);

        return array_map(
            static fn (GroupInvitation $i): PendingInvitationListItem => new PendingInvitationListItem(
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
