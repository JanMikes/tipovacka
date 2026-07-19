<?php

declare(strict_types=1);

namespace App\Command\UpdateGlobalCompetition;

use App\Enum\CompetitionMonetization;
use Symfony\Component\Uid\Uuid;

/**
 * Admin edit of a global competition's entry fee + monetization. Allowed only
 * while the owner is still the sole member (fee-lock). See .docs/DOMAIN.md
 * §Global competitions.
 */
final readonly class UpdateGlobalCompetitionCommand
{
    public function __construct(
        public Uuid $competitionId,
        public int $entryFeeCredits,
        public CompetitionMonetization $monetization,
    ) {
    }
}
