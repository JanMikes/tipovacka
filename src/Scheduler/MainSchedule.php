<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Command\ReconcilePremiumCompetitions\ReconcilePremiumCompetitionsCommand;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The app's single recurring schedule (transport `scheduler_default`, consumed
 * by the prod worker alongside `async` — see compose.yaml). S10 wires the first
 * task: premium reconciliation every 5 minutes. S11/S12 append their own
 * RecurringMessages here (reminder sweep, leaderboard snapshots).
 *
 * Stateful (cache-backed) so a worker that was briefly down runs only the last
 * missed tick instead of replaying every skipped one.
 */
#[AsSchedule('default')]
final class MainSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(
                RecurringMessage::every('5 minutes', new ReconcilePremiumCompetitionsCommand()),
            );
    }
}
