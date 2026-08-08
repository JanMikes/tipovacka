<?php

declare(strict_types=1);

namespace App\Command\SendMatchEvaluationDigests;

/**
 * Sweep: mail one digest per user for the matches evaluated since the last
 * sweep. Carries no payload — the window is derived from the notification rows
 * themselves, so a missed run simply catches up on the next one.
 */
final readonly class SendMatchEvaluationDigestsCommand
{
}
