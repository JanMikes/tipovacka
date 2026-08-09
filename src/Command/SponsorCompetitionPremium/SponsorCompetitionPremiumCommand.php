<?php

declare(strict_types=1);

namespace App\Command\SponsorCompetitionPremium;

use Symfony\Component\Uid\Uuid;

/**
 * An admin puts a PRIVATE group's premium on us, or takes that back.
 *
 * A global competition could always run on premium at no user's expense,
 * because an admin owns it and an admin's wallet is ours. A partička had no
 * equivalent: its organizer paid per player or lost premium at the first
 * kickoff. This is that decision for a partička — see .docs/DOMAIN.md
 * §Monetization.
 */
final readonly class SponsorCompetitionPremiumCommand
{
    public function __construct(
        public Uuid $competitionId,
        public Uuid $grantedById,
        public bool $sponsored = true,
    ) {
    }
}
