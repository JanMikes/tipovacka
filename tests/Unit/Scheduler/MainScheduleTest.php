<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use App\Command\ReconcilePremiumCompetitions\ReconcilePremiumCompetitionsCommand;
use App\Scheduler\MainSchedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Scheduler\Generator\MessageContext;

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
}
