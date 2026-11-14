<?php

declare(strict_types=1);

namespace App\Console;

use App\Command\SendEmailDigests\SendEmailDigestsCommand as SendDigestsMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Host-cron entry point for the hourly e-mail sweep (lily.srv
 * `apps/wtips/cron.d/wtips`, D30).
 *
 * The NAME is `app:match-digests:send` because that is what the crontab calls
 * — it was born as the digest of evaluated matches and must stay stable. What
 * it runs has grown: it now sends every mail that is one-for-many — the hourly
 * collapse of queued notifications and each player's daily summary. The alias
 * `app:email-digests:send` is the name to use by hand.
 */
#[AsCommand(
    name: 'app:match-digests:send',
    description: 'Send the due notification e-mail digests (hourly collapse + daily summaries).',
    aliases: ['app:email-digests:send'],
)]
final class SendEmailDigestsCommand extends Command
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

        $output->writeln('<info>E-mail digests sent.</info>');

        return Command::SUCCESS;
    }
}
