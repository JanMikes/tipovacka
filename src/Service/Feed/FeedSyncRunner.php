<?php

declare(strict_types=1);

namespace App\Service\Feed;

use App\Command\SyncMatchSourceFeed\SyncMatchSourceFeedCommand;
use App\Entity\MatchSource;
use App\Exception\FeedResultsOverdue;
use App\Repository\MatchSourceRepository;
use App\Repository\SportMatchRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * One sync pass over every feed-bound source: decide who is due, FETCH from the
 * provider (network, deliberately outside any transaction), then hand each
 * batch to {@see SyncMatchSourceFeedCommand} — one transaction per source, so a
 * single broken feed never blocks the rest.
 *
 * This is where the pass lives, not in the console command: the command is a
 * thin argument parser + renderer around this service, which is what makes the
 * whole pipeline testable without a Console tester.
 *
 * Warnings (unpaired team names, statuses we refuse to guess, cancellations
 * awaiting confirmation, past fixtures we declined to import, an overtime winner
 * a zdroj without prodloužení cannot hold) are logged at
 * warning level and reported; only real failures make {@see FeedSyncReport}
 * fail, because a missing team alias is hygiene, not an outage.
 *
 * Self-healing + watchdog (after the 2026-10-28 → 11-17 Premier League outage,
 * when a lapsed vendor subscription answered „no fixtures" for three weeks and
 * every pass reported success):
 *  - each provider is told the kickoff of the oldest feed-linked match still
 *    awaiting a result, and a windowed provider reaches back to it — so the
 *    results of an outage arrive by themselves once the feed is back;
 *  - every pass ends with the overdue check: a feed-linked match still without
 *    a result {@see OVERDUE_AFTER} after kickoff FAILS the pass (exit 1 → the
 *    lily cron alert + the Sentry cron monitor) and keeps failing it until the
 *    result is in; once an hour it is also logged as a dedicated
 *    {@see FeedResultsOverdue} Sentry issue, which the „wtips feed" alert rule
 *    e-mails on every occurrence (a user must never be the one to tell us). A healthy feed finishes a match ~2 h after kickoff (p95
 *    2.2 h across every provider, measured 2026-11-18), so this never fires on
 *    ordinary latency — only when nobody is going to deliver that result.
 */
final readonly class FeedSyncRunner
{
    /** A result still missing this long after kickoff is an incident, not latency. */
    public const string OVERDUE_AFTER = '-12 hours';

    /** How far back a provider is asked to heal; matches older than this need a human anyway. */
    private const string HEAL_LOOKBACK = '-120 days';

    /** The first five minutes of each hour: exactly one pass of the every-five-minutes cron. */
    private const int ALERT_MINUTES_PAST_HOUR = 5;

    public function __construct(
        private MatchSourceRepository $matchSources,
        private SportMatchRepository $sportMatches,
        private MatchDataProviderRegistry $providers,
        private FeedSynchronizer $synchronizer,
        private FeedPollPolicy $pollPolicy,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    /**
     * @param bool $force ignore the poll cadence and fetch every source now
     */
    public function run(?Uuid $onlySourceId = null, bool $dryRun = false, bool $force = false): FeedSyncReport
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $report = new FeedSyncReport();

        foreach ($this->matchSources->listFeedBound() as $source) {
            if (null !== $onlySourceId && !$source->id->equals($onlySourceId)) {
                continue;
            }

            $this->runOne($source, $report, $now, $dryRun, $force || null !== $onlySourceId);
        }

        $this->checkOverdueResults($report, $now, $onlySourceId);

        return $report;
    }

    private function checkOverdueResults(FeedSyncReport $report, \DateTimeImmutable $now, ?Uuid $onlySourceId): void
    {
        $overdue = $this->sportMatches->listOverdueFeedResults($now->modify(self::OVERDUE_AFTER), $onlySourceId);

        if ([] === $overdue) {
            return;
        }

        $labels = [];

        foreach ($overdue as $match) {
            $label = sprintf(
                '%s: %s – %s (%s UTC)',
                $match->matchSource->name,
                $match->homeTeam->name,
                $match->awayTeam->name,
                $match->kickoffAt->format('Y-m-d H:i'),
            );
            $labels[] = $label;
            $report->addOverdue($label);
        }

        $context = [
            'count' => count($overdue),
            'matches' => array_slice($labels, 0, 20),
        ];

        // ERROR once an hour — the top-of-the-hour pass of the */5 cron — so a
        // long outage is one Sentry issue with ~24 events a day, not ~300; every
        // other pass logs a warning (Sentry Logs) and still fails its exit code.
        if ((int) $now->format('i') < self::ALERT_MINUTES_PAST_HOUR) {
            $this->logger->error('Feed results are overdue — tips are not being evaluated.', $context + [
                'exception' => FeedResultsOverdue::matches(count($overdue), $labels[0]),
            ]);

            return;
        }

        $this->logger->warning('Feed results are overdue — tips are not being evaluated.', $context);
    }

    private function runOne(
        MatchSource $source,
        FeedSyncReport $report,
        \DateTimeImmutable $now,
        bool $dryRun,
        bool $force,
    ): void {
        $feedProvider = $source->feedProvider;

        if (null === $feedProvider) {
            return;
        }

        $provider = $this->providers->providerFor($feedProvider);

        if (null === $provider) {
            // Binding a source to a provider whose adapter has not shipped yet
            // is legal and silent-ish by design.
            $report->addSkipped($source, sprintf('no adapter for provider "%s"', $feedProvider->value));

            return;
        }

        if (!$force && !$this->pollPolicy->isDue($source, $now)) {
            $report->addSkipped($source, sprintf('not due (%s)', $this->pollPolicy->cadence($source, $now)));

            return;
        }

        $awaitingResultSince = $this->sportMatches->findOldestKickoffAwaitingFeedResult(
            $source->id,
            $now->modify(self::HEAL_LOOKBACK),
            $now,
        );

        try {
            $snapshots = $provider->fetchMatches($source, $awaitingResultSince);
        } catch (\Throwable $e) {
            $report->addFailed($source, $e->getMessage());
            $this->logger->error('Feed fetch failed.', [
                'exception' => $e,
                'matchSourceId' => (string) $source->id,
                'provider' => $feedProvider->value,
            ]);

            return;
        }

        $result = $dryRun
            ? $this->synchronizer->sync($source, $snapshots, apply: false)
            : $this->apply($source, $snapshots);

        if (!$result instanceof FeedSyncResult) {
            $report->addFailed($source, 'sync produced no result');

            return;
        }

        $this->logProblems($source, $result);
        $report->addSynced($source, count($snapshots), $result);
    }

    /**
     * @param list<MatchSnapshot> $snapshots
     */
    private function apply(MatchSource $source, array $snapshots): ?FeedSyncResult
    {
        $envelope = $this->commandBus->dispatch(new SyncMatchSourceFeedCommand(
            matchSourceId: $source->id,
            snapshots: $snapshots,
        ));

        $result = $envelope->last(HandledStamp::class)?->getResult();

        return $result instanceof FeedSyncResult ? $result : null;
    }

    private function logProblems(MatchSource $source, FeedSyncResult $result): void
    {
        $context = ['matchSourceId' => (string) $source->id, 'matchSource' => $source->name];

        if ([] !== $result->unresolvedTeams) {
            // Warning, not error: we pair everything we can and report the rest.
            // An admin adds the alias when they get to it; nobody is paged.
            $this->logger->warning('Feed team names could not be paired with the directory.', $context + [
                'teams' => $result->unresolvedTeams,
            ]);
        }

        if ([] !== $result->unknownStatus) {
            $this->logger->warning('Feed reported statuses the adapter does not map.', $context + [
                'matches' => $result->unknownStatus,
            ]);
        }

        if ([] !== $result->skippedPastKickoff) {
            $this->logger->warning('Feed listed matches that had already kicked off — not imported.', $context + [
                'count' => count($result->skippedPastKickoff),
            ]);
        }

        if ([] !== $result->overtimeNotPlayed) {
            $this->logger->warning('Feed reported a winner after extra time on a zdroj that plays none — winner dropped, tick "hraje se prodloužení" if it should.', $context + [
                'matches' => $result->overtimeNotPlayed,
            ]);
        }

        foreach ($result->errors as $error) {
            $this->logger->error('Feed snapshot could not be applied.', $context + ['error' => $error]);
        }
    }
}
