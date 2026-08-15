<?php

declare(strict_types=1);

namespace App\Logging;

use Sentry\Event;
use Sentry\EventHint;

/**
 * Drops Sentry issues raised by console commands only a human ever runs by hand.
 *
 * Symfony reports every console failure at CRITICAL, so a mistyped ad-hoc query
 * opens an issue indistinguishable from a broken cron: TIPOVACKA-P was
 * `dbal:run-sql` against „match_source" (the table is `match_sources`), typed
 * twice during a feed debugging session. That command appears in no cron.d entry
 * and no deploy step — its errors are the operator's own feedback loop, already
 * printed on the terminal they were typed into.
 *
 * Deliberately keyed on the COMMAND, not on the exception class: the very same
 * TableNotFoundException raised by `app:matches:sync` means a migration did not
 * land, and that must page.
 */
final readonly class DropInteractiveConsoleFailures
{
    private const array COMMANDS = [
        'dbal:run-sql',
    ];

    public function __invoke(Event $event, EventHint $hint): ?Event
    {
        $command = $event->getTags()['console.command'] ?? null;

        return \in_array($command, self::COMMANDS, true) ? null : $event;
    }
}
