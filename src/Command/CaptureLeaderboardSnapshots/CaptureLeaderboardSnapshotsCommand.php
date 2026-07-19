<?php

declare(strict_types=1);

namespace App\Command\CaptureLeaderboardSnapshots;

use Symfony\Component\Uid\Uuid;

/**
 * Capture the competition's current standings as the snapshot for `$day` (a
 * Prague calendar day). Idempotent per (competition, day) — re-running replaces
 * that day's rows, leaving other days intact. Dispatched by the daily sweep,
 * on `competition_ended`, and after a points recalculation. Routed to `async`
 * (see config/packages/messenger.php) so it never nests inside a triggering
 * command/event transaction.
 */
final readonly class CaptureLeaderboardSnapshotsCommand
{
    public function __construct(
        public Uuid $competitionId,
        public \DateTimeImmutable $day,
    ) {
    }
}
