<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;

/**
 * The competition is **fully over** — it includes at least one match and none of
 * its included matches can still produce a result (nothing Scheduled, Live or
 * Postponed is left; Finished and Cancelled both count as settled). Scope comes
 * from {@see \App\Service\Competition\CompetitionMatchProvider::isFullyOver}.
 *
 * Raised where an action would only make sense while the competition can still
 * move: buying a boost in a finished competition burns credits for an
 * entitlement with nothing left to unlock (BUGS.md B6). See
 * .docs/DOMAIN.md §Monetization.
 */
#[WithHttpStatus(409)]
final class CompetitionAlreadyOver extends \DomainException
{
    public static function forBoostPurchase(): self
    {
        return new self('Soutěž už skončila — vylepšení v ní už nemá co odemknout.');
    }
}
