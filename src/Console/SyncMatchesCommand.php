<?php

declare(strict_types=1);

namespace App\Console;

use App\Service\Feed\FeedSyncReport;
use App\Service\Feed\FeedSyncRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Host-cron entry point of the feed pipeline (lily.srv `apps/wtips/cron.d/wtips`,
 * D30 convention — keep the command name stable). Every five minutes; which
 * sources that tick is actually for is {@see \App\Service\Feed\FeedPollPolicy}'s
 * decision, not cron's.
 *
 * Deliberately thin: parse options, call {@see FeedSyncRunner}, render, map to an
 * exit code. All behaviour lives in the service so it is testable without a
 * console tester.
 *
 * EXIT CODES: 1 only on a REAL failure — a provider that could not be fetched or
 * a snapshot that could not be applied. Unpaired team names and unmapped
 * statuses are printed and logged at warning level but exit 0: they need a human
 * eventually, not a pager at 03:00.
 */
#[AsCommand(
    name: 'app:matches:sync',
    description: 'Synchronize feed-bound match sources from their external data providers.',
)]
final class SyncMatchesCommand extends Command
{
    public function __construct(
        private readonly FeedSyncRunner $runner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Sync only this match source (UUID); implies --force')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview the diff without writing anything')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ignore the poll cadence and fetch every bound source now');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $onlySource = $input->getOption('source');

        $report = $this->runner->run(
            onlySourceId: is_string($onlySource) && '' !== $onlySource ? Uuid::fromString($onlySource) : null,
            dryRun: (bool) $input->getOption('dry-run'),
            force: (bool) $input->getOption('force'),
        );

        $this->render($output, $report, (bool) $input->getOption('dry-run'));

        return $report->hasFailures ? Command::FAILURE : Command::SUCCESS;
    }

    private function render(OutputInterface $output, FeedSyncReport $report, bool $dryRun): void
    {
        if ([] === $report->synced && [] === $report->failed) {
            $output->writeln('<comment>No match source was due for a feed sync.</comment>');
        }

        foreach ($report->skipped as $entry) {
            $output->writeln(sprintf('<comment>%s: %s</comment>', $entry['source']->name, $entry['reason']));
        }

        foreach ($report->synced as $entry) {
            $result = $entry['result'];

            $output->writeln(sprintf(
                '<info>%s</info>%s: %d snapshots — %d created, %d kickoff moved, %d postponed, %d rescheduled, %d live, %d finished, %d corrected, %d unchanged',
                $entry['source']->name,
                $dryRun ? ' (dry run)' : '',
                $entry['snapshots'],
                count($result->created),
                count($result->kickoffMoved),
                count($result->postponed),
                count($result->rescheduled),
                count($result->liveUpdated),
                count($result->finished),
                count($result->corrected),
                $result->unchanged,
            ));

            foreach ($result->unresolvedTeams as $teamName) {
                $output->writeln(sprintf('  <comment>unresolved team — add an alias: "%s"</comment>', $teamName));
            }

            foreach ($result->unknownStatus as $label) {
                $output->writeln(sprintf('  <comment>unknown status: %s</comment>', $label));
            }

            foreach ($result->overtimeNotPlayed as $label) {
                $output->writeln(sprintf('  <comment>decided after extra time but the zdroj plays none — winner dropped: %s</comment>', $label));
            }

            foreach ($result->cancelledReported as $label) {
                $output->writeln(sprintf('  <comment>cancellation reported (manual action needed): %s</comment>', $label));
            }

            if ([] !== $result->skippedPastKickoff) {
                $output->writeln(sprintf(
                    '  <comment>%d already-played %s not imported (add by hand if wanted)</comment>',
                    count($result->skippedPastKickoff),
                    1 === count($result->skippedPastKickoff) ? 'match' : 'matches',
                ));
            }

            foreach ($result->needsAdoption as $message) {
                $output->writeln(sprintf('  <error>%s</error>', $message));
            }

            foreach ($result->errors as $message) {
                $output->writeln(sprintf('  <error>%s</error>', $message));
            }
        }

        foreach ($report->failed as $entry) {
            $output->writeln(sprintf('<error>%s: %s</error>', $entry['source']->name, $entry['error']));
        }
    }
}
