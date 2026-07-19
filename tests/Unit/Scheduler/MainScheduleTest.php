<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use App\Command\CaptureDailyLeaderboardSnapshots\CaptureDailyLeaderboardSnapshotsCommand;
use App\Command\ReconcilePremiumCompetitions\ReconcilePremiumCompetitionsCommand;
use App\Scheduler\MainSchedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

final class MainScheduleTest extends TestCase
{
    public function testScheduleRegistersPremiumReconciliation(): void
    {
        $schedule = (new MainSchedule(new ArrayAdapter()))->getSchedule();

        $recurringMessages = $schedule->getRecurringMessages();
        self::assertNotEmpty($recurringMessages, 'The schedule must register at least one recurring message.');

        $messages = [];
        foreach ($recurringMessages as $recurringMessage) {
            $context = new MessageContext(
                'default',
                $recurringMessage->getId(),
                $recurringMessage->getTrigger(),
                new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
            );

            foreach ($recurringMessage->getProvider()->getMessages($context) as $message) {
                $messages[] = $message;
            }
        }

        $reconciliation = array_filter(
            $messages,
            static fn (object $message): bool => $message instanceof ReconcilePremiumCompetitionsCommand,
        );

        self::assertCount(1, $reconciliation, 'MainSchedule must run premium reconciliation exactly once.');
    }

    public function testScheduleRegistersDailyLeaderboardSnapshotAtThreeAmPrague(): void
    {
        $schedule = (new MainSchedule(new ArrayAdapter()))->getSchedule();

        $trigger = null;
        foreach ($schedule->getRecurringMessages() as $recurringMessage) {
            $context = new MessageContext(
                'default',
                $recurringMessage->getId(),
                $recurringMessage->getTrigger(),
                new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
            );

            foreach ($recurringMessage->getProvider()->getMessages($context) as $message) {
                if ($message instanceof CaptureDailyLeaderboardSnapshotsCommand) {
                    $trigger = $recurringMessage->getTrigger();
                }
            }
        }

        self::assertInstanceOf(TriggerInterface::class, $trigger, 'MainSchedule must register the daily leaderboard-snapshot sweep.');

        // Fires daily at 03:00 Europe/Prague — the next run after midday is the
        // following day at 03:00 (in the Prague zone, not UTC).
        $nextRun = $trigger->getNextRunDate(new \DateTimeImmutable('2025-06-15 12:00:00', new \DateTimeZone('Europe/Prague')));
        self::assertNotNull($nextRun);
        $pragueNextRun = $nextRun->setTimezone(new \DateTimeZone('Europe/Prague'));
        self::assertSame('03:00', $pragueNextRun->format('H:i'));
        self::assertSame('2025-06-16', $pragueNextRun->format('Y-m-d'));
    }
}
