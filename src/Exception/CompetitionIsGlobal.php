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
}
