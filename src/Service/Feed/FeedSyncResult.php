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

    public bool $hasProblems {
        get => [] !== $this->unresolvedTeams || [] !== $this->errors || [] !== $this->unknownStatus;
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
