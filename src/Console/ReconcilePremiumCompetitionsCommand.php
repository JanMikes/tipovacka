<?php

declare(strict_types=1);

namespace App\Console;

use App\Command\ReconcilePremiumCompetitions\ReconcilePremiumCompetitionsCommand as ReconcilePremiumCompetitionsMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Host-cron entry point for premium reconciliation (every 5 minutes).
 *
 * Invoked by the box crontab (lily.srv `apps/wtips/cron.d/wtips`, D30 convention)
 * instead of symfony/scheduler, so ops can see/monitor the job as a real cron
 * entry. It only dispatches the same {@see ReconcilePremiumCompetitionsMessage}
 * the old App\Scheduler\MainSchedule (now removed) ran; the handler is idempotent,
 * so a standalone run is safe.
 */
#[AsCommand(
    name: 'app:premium:reconcile',
    description: 'Reconcile premium competitions at first kickoff (charge/refund/downgrade).',
)]
final class ReconcilePremiumCompetitionsCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandBus->dispatch(new ReconcilePremiumCompetitionsMessage());

        $output->writeln('<info>Premium competitions reconciled.</info>');

        return Command::SUCCESS;
    }
}
