<?php

declare(strict_types=1);

namespace App\Service\Feed;

use App\Entity\MatchSource;
use App\Entity\Player;
use App\Entity\SportMatch;
use App\Entity\Team;
use App\Enum\FeedMatchStatus;
use App\Repository\MatchEventRepository;
use App\Repository\PlayerRepository;
use App\Repository\SportMatchRepository;
use App\Service\Identity\ProvideIdentity;
use App\Service\Player\PlayerNameNormalizer;
use App\Service\SportMatch\MatchEventWriter;
use App\Service\Team\TeamResolver;
use App\Value\MatchEventInput;
use App\Value\PeriodScores;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Diffs provider snapshots against DB state for one feed-bound source and
 * applies the difference through the SAME entity operations the admin surfaces
 * use, so evaluation, notifications and tip-deadline pinning follow from the
 * recorded domain events exactly as they do for manual entry.
 *
 * Guarantees, in order of importance:
 *  - idempotent: a second pass over identical snapshots is all no-ops — no
 *    duplicate fixtures (externalId anchor), no re-evaluation churn (score and
 *    event-sheet equality is checked before any write);
 *  - never creates teams: an unknown team spelling parks the match in
 *    `unresolvedTeams` until an admin adds a TeamAlias (the pending-team gate);
 *  - never cancels: a feed's "cancelled" is reported, not applied — our
 *    cancellation voids guesses and cannot be undone, so it stays a human call;
 *  - per-snapshot fault isolation: one bad row lands in `errors`, the rest of
 *    the source still syncs.
 *
 * Callers: SyncMatchSourceFeedHandler (apply=true, inside the command
 * transaction) and app:matches:sync --dry-run (apply=false, read-only preview).
 */
final readonly class FeedSynchronizer
{
    /**
     * How far back an unknown, already-played fixture is still worth reporting.
     * Older ones belong to rounds this source never covered and are ignored in
     * silence — see createFromSnapshot().
     */
    private const string HISTORIC_CUTOFF = '-7 days';

    public function __construct(
        private SportMatchRepository $sportMatches,
        private MatchEventRepository $matchEvents,
        private PlayerRepository $players,
        private PlayerNameNormalizer $playerNames,
        private TeamResolver $teamResolver,
        private MatchEventWriter $matchEventWriter,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<MatchSnapshot> $snapshots
     */
    public function sync(MatchSource $source, array $snapshots, bool $apply): FeedSyncResult
    {
        $result = new FeedSyncResult(dryRun: !$apply);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $mayCreate = $this->isBridgedToThisFeed($source, $snapshots);

        if (!$mayCreate) {
            $result->addNeedsAdoption(sprintf(
                'Every stored match carries an id from another feed and none match this one — run "app:matches:adopt-external-ids %s" first. Nothing was created.',
                $source->id->toRfc4122(),
            ));
            $this->logger->error('Feed sync refused to create on a source that was never bridged to this feed.', [
                'matchSourceId' => (string) $source->id,
                'snapshots' => count($snapshots),
            ]);
        }

        $seenExternalIds = [];

        foreach ($snapshots as $snapshot) {
            if (isset($seenExternalIds[$snapshot->externalId])) {
                $result->addError(sprintf('Duplicate externalId "%s" in feed payload — row skipped.', $snapshot->externalId));

                continue;
            }
            $seenExternalIds[$snapshot->externalId] = true;

            try {
                $this->syncOne($source, $snapshot, $apply, $now, $result, $mayCreate);
            } catch (\DomainException $e) {
                $result->addError(sprintf('%s: %s', $snapshot->label(), $e->getMessage()));
                $this->logger->warning('Feed snapshot could not be applied.', [
                    'exception' => $e,
                    'matchSourceId' => (string) $source->id,
                    'externalId' => $snapshot->externalId,
                ]);
            }
        }

        return $result;
    }

    /**
     * Whether this source's stored matches belong to the feed it is bound to.
     *
     * The dangerous shape is a source every one of whose matches carries an
     * identifier from SOMEWHERE ELSE — a previous provider, or the synthetic ids
     * a seed invented. Then no fixture is ever recognised, every one looks new,
     * and the sync quietly builds a second season beside the one people are
     * tipping. That is not hypothetical: on 2026-08-08 Chance Liga was bound to
     * FAČR before it was bridged, the five-minute cron fired in between, and 220
     * duplicate matches plus 1760 notifications had to be deleted by hand.
     *
     * Matches with NO externalId are deliberately not counted: a hand-maintained
     * source that starts receiving feed fixtures is a legitimate, common case.
     * The signal is foreign ids, not the mere presence of matches.
     *
     * @param list<MatchSnapshot> $snapshots
     */
    private function isBridgedToThisFeed(MatchSource $source, array $snapshots): bool
    {
        if ([] === $snapshots) {
            return true;
        }

        $externalIds = array_map(static fn (MatchSnapshot $snapshot): string => $snapshot->externalId, $snapshots);

        if ($this->sportMatches->countLinkedToExternalIds($source->id, $externalIds) > 0) {
            return true;
        }

        return 0 === $this->sportMatches->countWithForeignExternalIds($source->id, $externalIds);
    }

    private function syncOne(
        MatchSource $source,
        MatchSnapshot $snapshot,
        bool $apply,
        \DateTimeImmutable $now,
        FeedSyncResult $result,
        bool $mayCreate,
    ): void {
        if (FeedMatchStatus::Unknown === $snapshot->status) {
            $result->addUnknownStatus(sprintf('%s [%s]', $snapshot->label(), $snapshot->rawStatus ?? '?'));
            $this->logger->warning('Feed snapshot has an unmapped status.', [
                'matchSourceId' => (string) $source->id,
                'externalId' => $snapshot->externalId,
                'rawStatus' => $snapshot->rawStatus,
            ]);

            return;
        }

        $existing = $this->sportMatches->findBySourceAndExternalId($source->id, $snapshot->externalId);

        if (null === $existing) {
            if ($mayCreate) {
                $this->createFromSnapshot($source, $snapshot, $apply, $now, $result);
            }

            return;
        }

        match ($snapshot->status) {
            FeedMatchStatus::Scheduled => $this->applyScheduled($existing, $snapshot, $apply, $now, $result),
            FeedMatchStatus::Live => $this->applyLive($existing, $snapshot, $apply, $now, $result),
            FeedMatchStatus::Finished => $this->applyFinished($existing, $snapshot, $apply, $now, $result),
            FeedMatchStatus::Postponed => $this->applyPostponed($existing, $snapshot, $apply, $now, $result),
            // Cancelled — Unknown already returned above.
            default => $this->reportCancellation($existing, $snapshot, $result),
        };
    }

    private function createFromSnapshot(
        MatchSource $source,
        MatchSnapshot $snapshot,
        bool $apply,
        \DateTimeImmutable $now,
        FeedSyncResult $result,
    ): void {
        if (FeedMatchStatus::Cancelled === $snapshot->status) {
            // Nothing to void, nothing worth showing — a fixture that was
            // cancelled before we ever saw it is simply not imported.
            $result->addCancelledReported(sprintf('%s (not imported)', $snapshot->label()));

            return;
        }

        // A feed lists the WHOLE season, including rounds played before this
        // source existed (FAČR serves all 240 Chance Liga zápasů against the 224
        // seeded from kolo 3; UEFA serves every qualifying round). Creating those
        // would drop already-played matches into a running soutěž that nobody
        // could ever have tipped — everyone scores zero on them, forever. Import
        // only what is still ahead; a past fixture that genuinely belongs here is
        // an admin's deliberate act.
        if ($snapshot->kickoffUtc <= $now) {
            // Only a RECENT miss is news. Rounds that finished long before this
            // source existed are simply not ours, and reporting them on every
            // poll would bury the one case worth looking at.
            if ($snapshot->kickoffUtc > $now->modify(self::HISTORIC_CUTOFF)) {
                $result->addSkippedPastKickoff($snapshot->label());
            }

            return;
        }

        $homeTeam = $this->teamResolver->findExisting($source, $snapshot->homeTeamName);
        $awayTeam = $this->teamResolver->findExisting($source, $snapshot->awayTeamName);

        if (!$homeTeam instanceof Team || !$awayTeam instanceof Team) {
            if (!$homeTeam instanceof Team) {
                $result->addUnresolvedTeam($snapshot->homeTeamName);
            }
            if (!$awayTeam instanceof Team) {
                $result->addUnresolvedTeam($snapshot->awayTeamName);
            }

            return;
        }

        if (!$apply) {
            $result->addCreated($snapshot->label());

            return;
        }

        $match = new SportMatch(
            id: $this->identity->next(),
            matchSource: $source,
            homeTeam: $homeTeam,
            awayTeam: $awayTeam,
            kickoffAt: $snapshot->kickoffUtc,
            venue: $snapshot->venue,
            createdAt: $now,
            round: $snapshot->round,
            isPlayoff: false,
            externalId: $snapshot->externalId,
        );
        $this->sportMatches->save($match);
        $result->addCreated($snapshot->label());

        // A fixture may already be beyond "scheduled" when first seen
        // (feed switched on mid-season) — bring it to its real state.
        match ($snapshot->status) {
            FeedMatchStatus::Postponed => $match->postponeTo($snapshot->kickoffUtc, $now),
            FeedMatchStatus::Live => $this->writeLiveScore($match, $snapshot, $now),
            FeedMatchStatus::Finished => $this->writeFinalScore($match, $snapshot, $now),
            default => null,
        };
    }

    private function applyScheduled(
        SportMatch $match,
        MatchSnapshot $snapshot,
        bool $apply,
        \DateTimeImmutable $now,
        FeedSyncResult $result,
    ): void {
        if ($match->isPostponed) {
            if ($apply) {
                $match->reschedule($snapshot->kickoffUtc, $now);
            }
            $result->addRescheduled($snapshot->label());

            return;
        }

        if ($match->isFinished || $match->isCancelled) {
            $result->addError(sprintf('%s: feed says scheduled but the match is %s here.', $snapshot->label(), $match->state->value));

            return;
        }

        if ($match->isLive) {
            // Out-of-order feed rows; the live/finished pass will catch up.
            $result->addUnchanged();

            return;
        }

        if ($match->kickoffAt->getTimestamp() === $snapshot->kickoffUtc->getTimestamp()) {
            $result->addUnchanged();

            return;
        }

        if ($apply) {
            // A kickoff move WITHOUT a postponed status is a fixture-time
            // correction (TV scheduling): state stays Scheduled and the
            // deadline resolver decides what follows (extend-only).
            $match->updateDetails(
                homeTeam: null,
                awayTeam: null,
                kickoffAt: $snapshot->kickoffUtc,
                venue: $snapshot->venue ?? $match->venue,
                now: $now,
                round: $snapshot->round ?? $match->round,
                isPlayoff: $match->isPlayoff,
            );
        }
        $result->addKickoffMoved($snapshot->label());
    }

    private function applyLive(
        SportMatch $match,
        MatchSnapshot $snapshot,
        bool $apply,
        \DateTimeImmutable $now,
        FeedSyncResult $result,
    ): void {
        if ($match->isFinished || $match->isCancelled) {
            // Feed lag after we already stored the final result — nothing to do.
            $result->addUnchanged();

            return;
        }

        if ($apply) {
            $this->writeLiveScore($match, $snapshot, $now);
        }
        $result->addLiveUpdated($snapshot->label());
    }

    private function applyFinished(
        SportMatch $match,
        MatchSnapshot $snapshot,
        bool $apply,
        \DateTimeImmutable $now,
        FeedSyncResult $result,
    ): void {
        if (null === $snapshot->homeScore || null === $snapshot->awayScore) {
            $result->addError(sprintf('%s: finished without scores.', $snapshot->label()));

            return;
        }

        if ($match->isFinished && $this->finalStateEquals($match, $snapshot)) {
            $result->addUnchanged();

            return;
        }

        $wasFinished = $match->isFinished;

        if ($apply) {
            $this->writeFinalScore($match, $snapshot, $now);
        }

        $wasFinished ? $result->addCorrected($snapshot->label()) : $result->addFinished($snapshot->label());
    }

    private function applyPostponed(
        SportMatch $match,
        MatchSnapshot $snapshot,
        bool $apply,
        \DateTimeImmutable $now,
        FeedSyncResult $result,
    ): void {
        if ($match->isFinished || $match->isCancelled) {
            $result->addError(sprintf('%s: feed says postponed but the match is %s here.', $snapshot->label(), $match->state->value));

            return;
        }

        if ($match->isPostponed && $match->kickoffAt->getTimestamp() === $snapshot->kickoffUtc->getTimestamp()) {
            $result->addUnchanged();

            return;
        }

        if ($apply) {
            $match->postponeTo($snapshot->kickoffUtc, $now);
        }
        $result->addPostponed($snapshot->label());
    }

    private function reportCancellation(SportMatch $match, MatchSnapshot $snapshot, FeedSyncResult $result): void
    {
        if ($match->isCancelled) {
            $result->addUnchanged();

            return;
        }

        // Cancelling voids guesses and is irreversible here — that stays a human
        // decision (admin runs the existing cancel action after checking).
        $result->addCancelledReported($snapshot->label());
        $this->logger->warning('Feed reports a cancellation — needs manual confirmation.', [
            'sportMatchId' => (string) $match->id,
            'externalId' => $snapshot->externalId,
        ]);
    }

    private function writeLiveScore(SportMatch $match, MatchSnapshot $snapshot, \DateTimeImmutable $now): void
    {
        if (null === $snapshot->homeScore || null === $snapshot->awayScore) {
            if ($match->isScheduled) {
                $match->beginLive($now);
            }

            return;
        }

        $match->updateLiveScore(
            homeScore: $snapshot->homeScore,
            awayScore: $snapshot->awayScore,
            periodScores: PeriodScores::fromNullableArray($snapshot->periodScores),
            now: $now,
        );
    }

    private function writeFinalScore(SportMatch $match, MatchSnapshot $snapshot, \DateTimeImmutable $now): void
    {
        $match->setFinalScore(
            homeScore: (int) $snapshot->homeScore,
            awayScore: (int) $snapshot->awayScore,
            periodScores: PeriodScores::fromNullableArray($snapshot->periodScores),
            overtimeHomeScore: $snapshot->overtimeHomeScore,
            overtimeAwayScore: $snapshot->overtimeAwayScore,
            now: $now,
        );

        // Null = score-only provider; stored events (possibly manually entered
        // scorers) must survive. See SetSportMatchFinalScoreCommand::$events.
        if (null !== $snapshot->events) {
            $this->matchEventWriter->replace($match, $snapshot->events, $now);
        }
    }

    /**
     * Whether a finished match already holds exactly this snapshot's result —
     * the guard that keeps repeated syncs from re-triggering evaluation
     * (every real write on a finished match fires SportMatchScoreUpdated,
     * which deletes and recomputes all evaluations).
     */
    private function finalStateEquals(SportMatch $match, MatchSnapshot $snapshot): bool
    {
        if ($match->homeScore !== $snapshot->homeScore || $match->awayScore !== $snapshot->awayScore) {
            return false;
        }

        if ($match->periodScores?->toArray() !== $snapshot->periodScores) {
            return false;
        }

        if ($match->overtimeHomeScore !== $snapshot->overtimeHomeScore
            || $match->overtimeAwayScore !== $snapshot->overtimeAwayScore) {
            return false;
        }

        if (null === $snapshot->events) {
            return true;
        }

        return $this->eventSheetEquals($match, $snapshot->events);
    }

    /**
     * Compares the STORED event sheet with the snapshot's, resolving snapshot
     * player names against the roster the same conservative way findOrCreate
     * does — so „J. Novák" in every feed payload keeps matching the stored
     * „Jan Novák" event instead of rewriting the sheet (and re-evaluating)
     * on every sync pass.
     *
     * @param list<MatchEventInput> $snapshotEvents
     */
    private function eventSheetEquals(SportMatch $match, array $snapshotEvents): bool
    {
        $stored = $this->matchEvents->listByMatch($match->id);

        if (count($stored) !== count($snapshotEvents)) {
            return false;
        }

        $rosters = [
            'home' => $this->players->listByTeam($match->homeTeam->id),
            'away' => $this->players->listByTeam($match->awayTeam->id),
        ];

        $storedKeys = array_map(
            fn ($event): string => sprintf(
                '%s|%s|%s|%s',
                $event->type->value,
                $event->side->value,
                $event->minute ?? '',
                (string) $event->player->id,
            ),
            $stored,
        );

        $snapshotKeys = array_map(
            function (MatchEventInput $event) use ($rosters): string {
                $roster = $rosters[$event->side->value];
                $player = $this->resolveRosterPlayer($roster, $event->playerName);

                return sprintf(
                    '%s|%s|%s|%s',
                    $event->type->value,
                    $event->side->value,
                    $event->minute ?? '',
                    $player instanceof Player ? (string) $player->id : 'new:'.mb_strtolower(trim($event->playerName)),
                );
            },
            $snapshotEvents,
        );

        sort($storedKeys);
        sort($snapshotKeys);

        return $storedKeys === $snapshotKeys;
    }

    /**
     * Find-only mirror of PlayerRepository::findOrCreate's matching (exact
     * case-insensitive, then a UNIQUE normalized/initial-form match) — used for
     * comparison, so it must never create.
     *
     * @param list<Player> $roster
     */
    private function resolveRosterPlayer(array $roster, string $name): ?Player
    {
        $needle = mb_strtolower(trim($name));

        foreach ($roster as $player) {
            if (mb_strtolower($player->name) === $needle) {
                return $player;
            }
        }

        $matches = array_values(array_filter(
            $roster,
            fn (Player $player): bool => $this->playerNames->matches($player->name, $name),
        ));

        return 1 === count($matches) ? $matches[0] : null;
    }
}
