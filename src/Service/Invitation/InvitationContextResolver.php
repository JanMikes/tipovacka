<?php

declare(strict_types=1);

namespace App\Service\Invitation;

use App\Enum\InvitationKind;
use App\Exception\InvalidInvitationToken;
use App\Exception\InvalidPin;
use App\Exception\InvalidShareableLink;
use App\Repository\CompetitionInvitationRepository;
use App\Repository\CompetitionRepository;
use Symfony\Component\Uid\Uuid;

final readonly class InvitationContextResolver
{
    public function __construct(
        private CompetitionInvitationRepository $invitationRepository,
        private CompetitionRepository $competitionRepository,
    ) {
    }

    /**
     * @throws InvalidInvitationToken when an email-kind token has no matching invitation,
     *                                or a global-kind one names no joinable global competition
     * @throws InvalidShareableLink   when a shareable-link-kind token has no matching competition
     * @throws InvalidPin             when a pin-kind token has no matching competition
     */
    public function resolve(InvitationKind $kind, string $token, \DateTimeImmutable $now): InvitationContext
    {
        return match ($kind) {
            InvitationKind::Email => $this->resolveEmailInvitation($token, $now),
            InvitationKind::ShareableLink => $this->resolveShareableLink($token, $now),
            InvitationKind::Pin => $this->resolvePin($token),
            InvitationKind::GlobalCompetition => $this->resolveGlobalCompetition($token),
        };
    }

    private function resolveEmailInvitation(string $token, \DateTimeImmutable $now): InvitationContext
    {
        $invitation = $this->invitationRepository->getByToken($token);

        $status = match (true) {
            $invitation->isRevoked => InvitationContextStatus::Revoked,
            $invitation->isAccepted => InvitationContextStatus::Accepted,
            $invitation->isExpiredAt($now) => InvitationContextStatus::Expired,
            $invitation->competition->scheduleIsComplete => InvitationContextStatus::MatchSourceCompleted,
            default => InvitationContextStatus::Active,
        };

        return new InvitationContext(
            kind: InvitationKind::Email,
            token: $token,
            competitionId: $invitation->competition->id,
            competitionName: $invitation->competition->name,
            matchSourceName: $invitation->competition->sourcesLabel,
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

        $status = $competition->scheduleIsComplete
            ? InvitationContextStatus::MatchSourceCompleted
            : InvitationContextStatus::Active;

        return new InvitationContext(
            kind: InvitationKind::ShareableLink,
            token: $token,
            competitionId: $competition->id,
            competitionName: $competition->name,
            matchSourceName: $competition->sourcesLabel,
            inviterNickname: $competition->owner->nickname,
            presetEmail: null,
            status: $status,
            expiresAt: null,
        );
    }

    /**
     * The global-competition invitation: its „token" is the competition's own UUID, which
     * is not a secret — the same competition is listed on the public „Soutěže" page.
     *
     * Anything that is NOT a joinable global competition resolves to „invalid", never to
     * a different outcome per reason: a private competition's UUID must not be
     * distinguishable here from a nonexistent one, or this page becomes a way to confirm
     * that a given id names a real partička.
     */
    private function resolveGlobalCompetition(string $competitionId): InvitationContext
    {
        if (!Uuid::isValid($competitionId)) {
            throw InvalidInvitationToken::forToken($competitionId);
        }

        $competition = $this->competitionRepository->find(Uuid::fromString($competitionId));

        if (null === $competition || !$competition->isGlobal || !$competition->isNotDeleted) {
            throw InvalidInvitationToken::forToken($competitionId);
        }

        return new InvitationContext(
            kind: InvitationKind::GlobalCompetition,
            token: $competitionId,
            competitionId: $competition->id,
            competitionName: $competition->name,
            matchSourceName: $competition->sourcesLabel,
            inviterNickname: null,
            presetEmail: null,
            status: $competition->scheduleIsComplete
                ? InvitationContextStatus::MatchSourceCompleted
                : InvitationContextStatus::Active,
            expiresAt: null,
            entryFeeCredits: $competition->entryFeeCredits,
        );
    }

    private function resolvePin(string $pin): InvitationContext
    {
        $competition = $this->competitionRepository->getByPin($pin);

        // Same defence as the shareable link: a global competition is joined by paying
        // the entry fee, never by knowing a secret, so a stray PIN must not resolve.
        if ($competition->isGlobal) {
            throw InvalidPin::create();
        }

        $status = $competition->scheduleIsComplete
            ? InvitationContextStatus::MatchSourceCompleted
            : InvitationContextStatus::Active;

        return new InvitationContext(
            kind: InvitationKind::Pin,
            token: $pin,
            competitionId: $competition->id,
            competitionName: $competition->name,
            matchSourceName: $competition->sourcesLabel,
            inviterNickname: $competition->owner->nickname,
            presetEmail: null,
            status: $status,
            expiresAt: null,
        );
    }
}
