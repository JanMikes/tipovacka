<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\Uid\Uuid;

/**
 * Raised when someone tries to join a global competition through a PIN or a
 * shareable link. Global competitions are joinable ONLY via the entry-fee flow —
 * a link/PIN join would create a fee-free membership (a money leak), so it is
 * rejected even if a leaked/stale token or an errant PIN somehow points at one.
 * See .docs/DOMAIN.md §Global competitions.
 */
#[WithHttpStatus(409)]
final class CompetitionIsGlobal extends \DomainException
{
    public static function joinViaShareableLink(Uuid $competitionId): self
    {
        return new self('Do globální soutěže se připojíte přes vstupné, ne odkazem.');
    }

    public static function joinViaPin(Uuid $competitionId): self
    {
        return new self('Do globální soutěže se připojíte přes vstupné, ne PINem.');
    }

    /**
     * The private-competition e-mail invitation pre-provisions the seat (a stub user
     * plus an active Membership) so the organizer can tip on the invitee's behalf
     * before they accept. For a global competition that would hand out a fee-free
     * membership — the same money leak the two factories above defend against — so the
     * global path mails the public invitation page instead and the invitee pays on
     * arrival ({@see \App\Command\InviteToGlobalCompetition\InviteToGlobalCompetitionHandler}).
     */
    public static function seatCannotBePreProvisioned(Uuid $competitionId): self
    {
        return new self(sprintf(
            'Soutěž "%s" je globální — pozvánka do ní nesmí předem vytvořit členství, platí se vstupné.',
            $competitionId->toRfc4122(),
        ));
    }

    /**
     * A global competition's scope is an admin decision taken in the admin area
     * ({@see \App\Command\UpdateGlobalCompetition\UpdateGlobalCompetitionHandler}):
     * players joined it under advertised terms, and it may never grow a private
     * „vlastní zápasy" zdroj. The organizer basket screen is therefore closed to it.
     */
    public static function scopeIsAdminOnly(): self
    {
        return new self('Rozsah zápasů globální soutěže se upravuje jen v administraci.');
    }

    /**
     * Sponsorship exists to give a PARTIČKA what a global competition already
     * has — an owner whose wallet is ours. A global competition is therefore
     * never sponsored: it is edited in the admin area, where its monetization
     * and entry fee are set together, and flipping it to premium from here
     * would change advertised terms players joined under.
     */
    public static function premiumIsAlreadyOnUs(): self
    {
        return new self('Globální soutěž se nesponzoruje — její monetizaci nastavíte v administraci soutěže.');
    }
}
