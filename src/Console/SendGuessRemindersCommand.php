<?php

declare(strict_types=1);

namespace App\Console;

use App\Command\SendGuessReminders\SendGuessRemindersCommand as SendGuessRemindersMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Host-cron entry point for the hourly guess-reminder sweep.
 *
 * Invoked by the box crontab (lily.srv `apps/wtips/cron.d/wtips`, D30 convention)
 * instead of symfony/scheduler, so ops can see/monitor the job as a real cron
 * entry. It only dispatches the same {@see SendGuessRemindersMessage} the old
 * App\Scheduler\MainSchedule (now removed) ran; the handler dedups per
 * (user, competition, deadline-day), so a standalone run is safe.
 */
#[AsCommand(
    name: 'app:guess-reminders:send',
    description: 'Send guess-deadline reminder notifications.',
)]
final class SendGuessRemindersCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandBus->dispatch(new SendGuessRemindersMessage());

        $output->writeln('<info>Guess reminders sent.</info>');

        return Command::SUCCESS;
    }
}
