<?php

declare(strict_types=1);

namespace App\Service\Feed;

use App\Entity\MatchSource;

/**
 * What one `app:matches:sync` pass did across every source it touched. The
 * console renders it; the exit code reads {@see hasFailures}. Sources that were
 * not due (poll policy) or have no adapter yet are carried as skips, so a dry
 * run explains its own quietness instead of printing nothing.
 */
final class FeedSyncReport
{
    /** @var list<array{source: MatchSource, snapshots: int, result: FeedSyncResult}> */
    public private(set) array $synced = [];

    /** @var list<array{source: MatchSource, reason: string}> */
    public private(set) array $skipped = [];

    /** @var list<array{source: MatchSource, error: string}> */
    public private(set) array $failed = [];

    public function addSynced(MatchSource $source, int $snapshots, FeedSyncResult $result): void
    {
        $this->synced[] = ['source' => $source, 'snapshots' => $snapshots, 'result' => $result];
    }

    public function addSkipped(MatchSource $source, string $reason): void
    {
        $this->skipped[] = ['source' => $source, 'reason' => $reason];
    }

    public function addFailed(MatchSource $source, string $error): void
    {
        $this->failed[] = ['source' => $source, 'error' => $error];
    }

    /**
     * Fetch errors and unapplicable snapshots — and nothing else. Unresolved
     * team names and unmapped statuses are warnings by explicit decision: we
     * pair everything we can and report the rest, but a missing alias is not an
     * outage and must not page anyone at 03:00.
     */
    public bool $hasFailures {
        get {
            if ([] !== $this->failed) {
                return true;
            }

            foreach ($this->synced as $entry) {
                if ($entry['result']->hasFailures) {
                    return true;
                }
            }

            return false;
        }
    }
}
