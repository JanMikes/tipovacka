<?php

declare(strict_types=1);

namespace App\Command\ReconcilePremiumCompetitions;

/**
 * Sweep every not-yet-reconciled premium competition whose start moment has
 * passed and settle it (confirm when all charges are covered; refund all +
 * downgrade to boosts when any is uncovered). Runs every 5 minutes via
 * {@see \App\Scheduler\MainSchedule}; also directly dispatchable. Idempotent.
 */
final readonly class ReconcilePremiumCompetitionsCommand
{
}
