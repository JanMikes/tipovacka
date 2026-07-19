<?php

declare(strict_types=1);

namespace App\Service\Invitation;

use App\Enum\InvitationKind;
use App\Exception\InvalidInvitationToken;
use App\Exception\InvalidShareableLink;
use App\Repository\CompetitionInvitationRepository;
use App\Repository\CompetitionRepository;

final readonly class InvitationContextResolver
{
    public function __construct(
        private CompetitionInvitationRepository $invitationRepository,
        private CompetitionRepository $competitionRepository,
    ) {
    }

    /**
     * @throws InvalidInvitationToken when an email-kind token has no matching invitation
     * @throws InvalidShareableLink   when a shareable-link-kind token has no matching competition
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
            $invitation->competition->matchSource->isCompleted => InvitationContextStatus::MatchSourceCompleted,
            default => InvitationContextStatus::Active,
        };

        return new InvitationContext(
            kind: InvitationKind::Email,
            token: $token,
            competitionId: $invitation->competition->id,
            competitionName: $invitation->competition->name,
            matchSourceName: $invitation->competition->matchSource->name,
            inviterNickname: $invitation->inviter->nickname,
            presetEmail: $invitation->email,
            status: $status,
            expiresAt: $invitation->expiresAt,
        );
    }

    private function resolveShareableLink(string $token, \DateTimeImmutable $now): InvitationContext
    {
        $competition = $this->competitionRepository->getByShareableLinkToken($token);

        // Global competitions never mint shareable-link tokens; a leaked/stale one
        // must not resolve to a joinable context (join is entry-fee only). Treat it
        // as an invalid link — the landing page renders the „not found" state.
        if ($competition->isGlobal) {
            throw InvalidShareableLink::create();
        }

        $status = $competition->matchSource->isCompleted
            ? InvitationContextStatus::MatchSourceCompleted
            : InvitationContextStatus::Active;

        return new InvitationContext(
            kind: InvitationKind::ShareableLink,
            token: $token,
            competitionId: $competition->id,
            competitionName: $competition->name,
            matchSourceName: $competition->matchSource->name,
            inviterNickname: $competition->owner->nickname,
            presetEmail: null,
            status: $status,
            expiresAt: null,
        );
    }
}
