<?php

declare(strict_types=1);

namespace App\Service\Feed;

/**
 * What one sync pass did (or, on a dry run, would do) to one MatchSource.
 * Buckets hold human-readable match labels („Sparta Praha – Slavia Praha (8327)");
 * the console prints them and the caller can alert on unresolvedTeams/errors.
 */
final class FeedSyncResult
{
    /** @var list<string> */
    public private(set) array $created = [];

    /** @var list<string> */
    public private(set) array $kickoffMoved = [];

    /** @var list<string> */
    public private(set) array $postponed = [];

    /** @var list<string> */
    public private(set) array $rescheduled = [];

    /** @var list<string> */
    public private(set) array $liveUpdated = [];

    /** @var list<string> */
    public private(set) array $finished = [];

    /** @var list<string> */
    public private(set) array $corrected = [];

    /** Cancellations are never auto-applied (terminal state) — only reported for admin action. */
    /** @var list<string> */
    public private(set) array $cancelledReported = [];

    /**
     * Feed rows for matches that had already kicked off when we first saw them.
     * Never created (nobody could have tipped them), only reported — an admin
     * adds them by hand if the soutěž really should include them.
     *
     * @var list<string>
     */
    public private(set) array $skippedPastKickoff = [];

    /**
     * Feed rows whose fixture is already stored under a DIFFERENT identifier —
     * the source is bound to this feed but was never bridged to it
     * (app:matches:adopt-external-ids). Never created: that is how a duplicate
     * season gets built next to the one people are tipping.
     *
     * @var list<string>
     */
    public private(set) array $needsAdoption = [];

    /** Feed team spellings with no directory/alias match — the pending-team gate. */
    /** @var list<string> */
    public private(set) array $unresolvedTeams = [];

    /** @var list<string> */
    public private(set) array $unknownStatus = [];

    /** @var list<string> */
    public private(set) array $errors = [];

    public private(set) int $unchanged = 0;

    public function __construct(
        public readonly bool $dryRun,
    ) {
    }

    /**
     * A REAL failure — something the feed asked for could not be applied. This
     * is the only bucket that fails the cron (and therefore pages): the rest are
     * pairing/hygiene gaps that a human resolves at their own pace.
     */
    public bool $hasFailures {
        get => [] !== $this->errors || [] !== $this->needsAdoption;
    }

    /**
     * Attention-worthy but not a failure: a team spelling we could not pair, a
     * status we refuse to guess, a cancellation needing confirmation, a past
     * fixture we declined to import. Logged at warning level — visible in the
     * logs, deliberately below Sentry's issue bar.
     */
    public bool $hasWarnings {
        get => [] !== $this->unresolvedTeams
            || [] !== $this->unknownStatus
            || [] !== $this->cancelledReported
            || [] !== $this->skippedPastKickoff;
    }

    public function addCreated(string $label): void
    {
        $this->created[] = $label;
    }

    public function addKickoffMoved(string $label): void
    {
        $this->kickoffMoved[] = $label;
    }

    public function addPostponed(string $label): void
    {
        $this->postponed[] = $label;
    }

    public function addRescheduled(string $label): void
    {
        $this->rescheduled[] = $label;
    }

    public function addLiveUpdated(string $label): void
    {
        $this->liveUpdated[] = $label;
    }

    public function addFinished(string $label): void
    {
        $this->finished[] = $label;
    }

    public function addCorrected(string $label): void
    {
        $this->corrected[] = $label;
    }

    public function addCancelledReported(string $label): void
    {
        $this->cancelledReported[] = $label;
    }

    public function addSkippedPastKickoff(string $label): void
    {
        $this->skippedPastKickoff[] = $label;
    }

    public function addNeedsAdoption(string $label): void
    {
        $this->needsAdoption[] = $label;
    }

    public function addUnresolvedTeam(string $teamName): void
    {
        if (!in_array($teamName, $this->unresolvedTeams, true)) {
            $this->unresolvedTeams[] = $teamName;
        }
    }

    public function addUnknownStatus(string $label): void
    {
        $this->unknownStatus[] = $label;
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function addUnchanged(): void
    {
        ++$this->unchanged;
    }
}
