<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logging;

use App\Logging\DropInteractiveConsoleFailures;
use PHPUnit\Framework\TestCase;
use Sentry\Event;
use Sentry\EventHint;

final class DropInteractiveConsoleFailuresTest extends TestCase
{
    public function testAHandRunQueryFailureNeverBecomesAnIssue(): void
    {
        self::assertNull($this->filter('dbal:run-sql'));
    }

    /**
     * The cron commands are the whole point of the tag check: the same
     * TableNotFoundException from `app:matches:sync` means a migration did not
     * land, and must still page.
     */
    public function testACronCommandFailureStillReaches(): void
    {
        self::assertNotNull($this->filter('app:matches:sync'));
    }

    public function testAnEventWithNoCommandTagIsUntouched(): void
    {
        self::assertNotNull($this->filter(null));
    }

    private function filter(?string $command): ?Event
    {
        $event = Event::createEvent();

        if (null !== $command) {
            $event->setTags(['console.command' => $command]);
        }

        return (new DropInteractiveConsoleFailures())($event, EventHint::fromArray([]));
    }
}
