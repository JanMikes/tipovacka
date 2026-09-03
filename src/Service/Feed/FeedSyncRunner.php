<?php

declare(strict_types=1);

namespace App\Service\Feed;

use App\Command\SyncMatchSourceFeed\SyncMatchSourceFeedCommand;
use App\Entity\MatchSource;
use App\Repository\MatchSourceRepository;
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
 */
final readonly class FeedSyncRunner
{
    public function __construct(
        private MatchSourceRepository $matchSources,
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

        return $report;
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

        try {
            $snapshots = $provider->fetchMatches($source);
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
