<?php

declare(strict_types=1);

namespace App\Command\CaptureDailyLeaderboardSnapshots;

/**
 * Daily leaderboard-snapshot sweep: for every competition whose standings may
 * have moved since its last snapshot (≥1 evaluation since), dispatch a
 * {@see \App\Command\CaptureLeaderboardSnapshots\CaptureLeaderboardSnapshotsCommand}
 * for TODAY (Prague). Runs 03:00 Europe/Prague via {@see \App\Scheduler\MainSchedule};
 * also directly dispatchable. Idempotent (today's rows are upserted).
 */
final readonly class CaptureDailyLeaderboardSnapshotsCommand
{
}
