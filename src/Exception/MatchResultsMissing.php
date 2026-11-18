<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Matches of curated zdroje that were played long ago and still have no result —
 * their tips cannot be evaluated. Never thrown: the daily
 * `app:matches:check-results` logs it at ERROR so it is ONE dedicated Sentry
 * issue (mailed by the „wtips feed" alert rule) next to the mail to admins.
 * Covers what the feed watchdog ({@see FeedResultsOverdue}) cannot: manually
 * managed zdroje, where nobody but an admin will ever enter the score.
 */
final class MatchResultsMissing extends \RuntimeException
{
    public static function inCuratedSources(int $count, string $oldest): self
    {
        return new self(sprintf(
            '%d played %s of curated zdroje still without a result — tips are not being evaluated (oldest: %s).',
            $count,
            1 === $count ? 'match' : 'matches',
            $oldest,
        ));
    }
}
