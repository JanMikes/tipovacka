<?php

declare(strict_types=1);

namespace App\Service\Invitation;

use App\Enum\InvitationKind;
use App\Exception\InvalidInvitationToken;
use App\Exception\InvalidShareableLink;
use App\Repository\GroupInvitationRepository;
use App\Repository\GroupRepository;

final readonly class InvitationContextResolver
{
    public function __construct(
        private GroupInvitationRepository $invitationRepository,
        private GroupRepository $groupRepository,
    ) {
    }

    /**
     * @throws InvalidInvitationToken when an email-kind token has no matching invitation
     * @throws InvalidShareableLink   when a shareable-link-kind token has no matching group
     */
    public function resolve(InvitationKind $kind, string $token, \DateTimeImmutable $now): InvitationContext
    {
        return match ($kind) {
            InvitationKind::Email => $this->resolveEmailInvitation($token, $now),
            InvitationKind::ShareableLink => $this->resolveShareableLink($token, $now),
        };
    }

    private function resolveEmailInvitation(string $token, \DateTimeImmutable $now): InvitationContext
    {
        $invitation = $this->invitationRepository->getByToken($token);

        $status = match (true) {
            $invitation->isRevoked => InvitationContextStatus::Revoked,
            $invitation->isAccepted => InvitationContextStatus::Accepted,
            $invitation->isExpiredAt($now) => InvitationContextStatus::Expired,
            $invitation->group->tournament->isFinished => InvitationContextStatus::TournamentFinished,
            default => InvitationContextStatus::Active,
        };

        return new InvitationContext(
            kind: InvitationKind::Email,
            token: $token,
            groupId: $invitation->group->id,
            groupName: $invitation->group->name,
            tournamentName: $invitation->group->tournament->name,
            inviterNickname: $invitation->inviter->nickname,
            presetEmail: $invitation->email,
            status: $status,
            expiresAt: $invitation->expiresAt,
        );
    }

    private function resolveShareableLink(string $token, \DateTimeImmutable $now): InvitationContext
    {
        $group = $this->groupRepository->getByShareableLinkToken($token);

        $status = $group->tournament->isFinished
            ? InvitationContextStatus::TournamentFinished
            : InvitationContextStatus::Active;

        return new InvitationContext(
            kind: InvitationKind::ShareableLink,
            token: $token,
            groupId: $group->id,
            groupName: $group->name,
            tournamentName: $group->tournament->name,
            inviterNickname: $group->owner->nickname,
            presetEmail: null,
            status: $status,
            expiresAt: null,
        );
    }
}
