<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Played matches whose result the feed has not delivered long after kickoff —
 * their tips are not being evaluated. Never thrown: it is logged (ERROR, once
 * an hour) so it becomes ONE dedicated Sentry issue that the „wtips feed"
 * alert rule mails on every occurrence until the results are in. Kept apart
 * from the cron-monitor issue on purpose: that one flaps on infra noise, and a
 * real outage must not look like more of the same.
 */
final class FeedResultsOverdue extends \RuntimeException
{
    public static function matches(int $count, string $oldest): self
    {
        return new self(sprintf(
            '%d played %s still without a result 12+ h after kickoff — tips are not being evaluated (oldest: %s).',
            $count,
            1 === $count ? 'match has' : 'matches have',
            $oldest,
        ));
    }
}
