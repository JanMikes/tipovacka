<?php

declare(strict_types=1);

namespace App\Command\SendEmailDigests;

/**
 * Sweep: send every player the ONE mail that is due to them right now — the
 * hourly collapse of queued notifications, or their daily summary. Carries no
 * payload: what is due is derived from the queued rows and the players'
 * settings, so a missed run simply catches up on the next one.
 */
final readonly class SendEmailDigestsCommand
{
}
