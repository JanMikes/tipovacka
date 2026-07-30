<?php

declare(strict_types=1);

namespace App\Command\MarkBoostIntroSeen;

use Symfony\Component\Uid\Uuid;

/**
 * The member dismissed the first-visit boost-price modal on a competition's
 * detail page (item 19). Stamps their `Membership` so it never opens again —
 * per user per competition, and in the database, so the dismissal survives a
 * fresh session. See .docs/ui-nav/items/19-page-competition-detail-pass.md.
 */
final readonly class MarkBoostIntroSeenCommand
{
    public function __construct(
        public Uuid $userId,
        public Uuid $competitionId,
    ) {
    }
}
