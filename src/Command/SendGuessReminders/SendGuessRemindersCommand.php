<?php

declare(strict_types=1);

namespace App\Command\SendGuessReminders;

/**
 * Hourly reminder sweep (also directly dispatchable in tests). Notifies each
 * active member of each competition about matches whose effective tip deadline
 * falls within the next 24 h and for which they have not tipped yet. Idempotent
 * per (user, competition, deadline-day) — safe to re-run every hour.
 */
final readonly class SendGuessRemindersCommand
{
}
