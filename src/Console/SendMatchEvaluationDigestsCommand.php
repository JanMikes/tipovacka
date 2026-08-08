<?php

declare(strict_types=1);

namespace App\Console;

use App\Command\SendMatchEvaluationDigests\SendMatchEvaluationDigestsCommand as SendDigestsMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Host-cron entry point for the hourly match-evaluation digest (lily.srv
 * `apps/wtips/cron.d/wtips`, D30 — keep the name stable).
 *
 * Sends the e-mail half of `match_evaluated`: one mail per user covering every
 * match evaluated since their last digest, so an automated feed finishing a
 * whole round cannot turn into one mail per zápas. In-app rows are written
 * immediately by the event handler and are untouched by this job.
 */
#[AsCommand(
    name: 'app:match-digests:send',
    description: 'Send the e-mail digest of recently evaluated matches.',
)]
final class SendMatchEvaluationDigestsCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandBus->dispatch(new SendDigestsMessage());

        $output->writeln('<info>Match evaluation digests sent.</info>');

        return Command::SUCCESS;
    }
}
