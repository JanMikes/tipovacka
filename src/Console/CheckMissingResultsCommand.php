<?php

declare(strict_types=1);

namespace App\Console;

use App\Service\SportMatch\MissingResultsCheck;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Host-cron entry point (lily.srv `apps/wtips/cron.d/wtips`, daily) of
 * {@see MissingResultsCheck}: played matches whose tips cannot be evaluated.
 * Exit 1 while a CURATED zdroj has one — the check also logs a Sentry issue and
 * mails every admin. Private zdroje are reported, never a failure.
 */
#[AsCommand(
    name: 'app:matches:check-results',
    description: 'Report played matches still without a result (Sentry + mail to admins).',
)]
final class CheckMissingResultsCommand extends Command
{
    public function __construct(
        private readonly MissingResultsCheck $check,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only print — no Sentry issue, no mail');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->check->run(notify: !$input->getOption('dry-run'));

        if ($report->isEmpty) {
            $output->writeln('<info>Every played match has a result.</info>');

            return Command::SUCCESS;
        }

        foreach ($report->curated as $missing) {
            $output->writeln(sprintf('<error>missing result: %s — %d tips</error>', $missing->label, $missing->tips));
        }

        foreach ($report->privateRecent as $missing) {
            $output->writeln(sprintf('<comment>private zdroj, organizer has not entered: %s — %d tips</comment>', $missing->label, $missing->tips));
        }

        return [] === $report->curated ? Command::SUCCESS : Command::FAILURE;
    }
}
