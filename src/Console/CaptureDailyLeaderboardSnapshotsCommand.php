<?php

declare(strict_types=1);

namespace App\Console;

use App\Command\CaptureDailyLeaderboardSnapshots\CaptureDailyLeaderboardSnapshotsCommand as CaptureDailyLeaderboardSnapshotsMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Host-cron entry point for the daily leaderboard-snapshot sweep (03:00 Prague).
 *
 * Invoked by the box crontab (lily.srv `apps/wtips/cron.d/wtips`, D30 convention)
 * instead of symfony/scheduler, so ops can see/monitor the job as a real cron
 * entry. It only dispatches the same {@see CaptureDailyLeaderboardSnapshotsMessage}
 * the old App\Scheduler\MainSchedule (now removed) ran; that handler fans out
 * per-competition async capture commands (consumed by the persistent messenger-consumer
 * worker) and is idempotent per (competition, day), so a standalone run is safe.
 */
#[AsCommand(
    name: 'app:leaderboard:capture-snapshots',
    description: 'Capture daily leaderboard snapshots for competitions with new evaluations.',
)]
final class CaptureDailyLeaderboardSnapshotsCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandBus->dispatch(new CaptureDailyLeaderboardSnapshotsMessage());

        $output->writeln('<info>Daily leaderboard snapshots captured.</info>');

        return Command::SUCCESS;
    }
}
