<?php

declare(strict_types=1);

namespace App\Console;

use App\Command\SyncMatchSourceFeed\SyncMatchSourceFeedCommand;
use App\Entity\MatchSource;
use App\Repository\MatchSourceRepository;
use App\Service\Feed\FeedSynchronizer;
use App\Service\Feed\FeedSyncResult;
use App\Service\Feed\MatchDataProviderRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;

/**
 * Host-cron entry point of the feed pipeline (lily.srv `apps/wtips/cron.d/wtips`,
 * D30 convention — keep the command name stable). For every feed-bound curated
 * source it FETCHES snapshots from the source's provider (network, outside any
 * transaction), then dispatches {@see SyncMatchSourceFeedCommand} — one
 * transaction per source, so one broken source never blocks the rest.
 * `--dry-run` previews the diff read-only without touching the bus.
 */
#[AsCommand(
    name: 'app:matches:sync',
    description: 'Synchronize feed-bound match sources from their external data providers.',
)]
final class SyncMatchesCommand extends Command
{
    public function __construct(
        private readonly MatchSourceRepository $matchSources,
        private readonly MatchDataProviderRegistry $providers,
        private readonly FeedSynchronizer $synchronizer,
        private readonly LoggerInterface $logger,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Sync only this match source (UUID)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview the diff without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $onlySource = $input->getOption('source');

        $sources = $this->matchSources->listFeedBound();

        if (is_string($onlySource) && '' !== $onlySource) {
            $onlyId = Uuid::fromString($onlySource);
            $sources = array_values(array_filter(
                $sources,
                static fn (MatchSource $source): bool => $source->id->equals($onlyId),
            ));
        }

        if ([] === $sources) {
            $output->writeln('<comment>No feed-bound match sources to sync.</comment>');

            return Command::SUCCESS;
        }

        $failures = 0;

        foreach ($sources as $source) {
            $feedProvider = $source->feedProvider;
            if (null === $feedProvider) {
                continue;
            }

            $provider = $this->providers->providerFor($feedProvider);
            if (null === $provider) {
                $output->writeln(sprintf(
                    '<comment>%s: no adapter implemented for provider "%s" — skipped.</comment>',
                    $source->name,
                    $feedProvider->value,
                ));

                continue;
            }

            try {
                $snapshots = $provider->fetchMatches($source);
            } catch (\Throwable $e) {
                ++$failures;
                $output->writeln(sprintf('<error>%s: fetch failed — %s</error>', $source->name, $e->getMessage()));
                $this->logger->error('Feed fetch failed.', [
                    'exception' => $e,
                    'matchSourceId' => (string) $source->id,
                    'provider' => $feedProvider->value,
                ]);

                continue;
            }

            if ($dryRun) {
                $result = $this->synchronizer->sync($source, $snapshots, apply: false);
            } else {
                $envelope = $this->commandBus->dispatch(new SyncMatchSourceFeedCommand(
                    matchSourceId: $source->id,
                    snapshots: $snapshots,
                ));

                $handled = $envelope->last(HandledStamp::class);
                $result = $handled?->getResult();

                if (!$result instanceof FeedSyncResult) {
                    ++$failures;
                    $output->writeln(sprintf('<error>%s: sync produced no result.</error>', $source->name));

                    continue;
                }
            }

            $this->printResult($output, $source, count($snapshots), $result);

            if ($result->hasProblems) {
                ++$failures;
            }
        }

        return 0 === $failures ? Command::SUCCESS : Command::FAILURE;
    }

    private function printResult(OutputInterface $output, MatchSource $source, int $snapshotCount, FeedSyncResult $result): void
    {
        $output->writeln(sprintf(
            '<info>%s</info>%s: %d snapshots — %d created, %d kickoff moved, %d postponed, %d rescheduled, %d live, %d finished, %d corrected, %d unchanged',
            $source->name,
            $result->dryRun ? ' (dry run)' : '',
            $snapshotCount,
            count($result->created),
            count($result->kickoffMoved),
            count($result->postponed),
            count($result->rescheduled),
            count($result->liveUpdated),
            count($result->finished),
            count($result->corrected),
            $result->unchanged,
        ));

        foreach ($result->cancelledReported as $label) {
            $output->writeln(sprintf('  <comment>cancellation reported (manual action needed): %s</comment>', $label));
        }

        foreach ($result->unresolvedTeams as $teamName) {
            $output->writeln(sprintf('  <comment>unresolved team — add an alias: "%s"</comment>', $teamName));
        }

        foreach ($result->unknownStatus as $label) {
            $output->writeln(sprintf('  <comment>unknown status: %s</comment>', $label));
        }

        foreach ($result->errors as $message) {
            $output->writeln(sprintf('  <error>%s</error>', $message));
        }
    }
}
