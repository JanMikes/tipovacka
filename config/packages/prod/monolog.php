<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sentry\Monolog\BreadcrumbHandler;
use Sentry\Monolog\ExceptionToSentryIssueHandler;
use Sentry\Monolog\LogToSentryIssueHandler;
use Sentry\SentryBundle\Monolog\LogsHandler;

return App::config([
    'monolog' => [
        // All four Sentry handlers bubble, so every record is offered to each of
        // them; they select by level and by whether the record carries a Throwable.
        'handlers' => [
            // INFO+ as breadcrumbs — the trail shown on whichever issue comes next.
            'sentry_breadcrumbs' => [
                'type' => 'service',
                'id' => BreadcrumbHandler::class,
                'channels' => ['!deprecation'],
            ],
            // INFO+ as Sentry structured Logs (needs options.enable_logs).
            // Skips records carrying a Throwable — those become issues below.
            'sentry_logs' => [
                'type' => 'service',
                'id' => LogsHandler::class,
                'channels' => ['!deprecation'],
            ],
            // ERROR+ WITH a Throwable in context → issue with stack trace/grouping.
            'sentry_exceptions' => [
                'type' => 'service',
                'id' => ExceptionToSentryIssueHandler::class,
                'channels' => ['!deprecation'],
            ],
            // ERROR+ WITHOUT a Throwable → issue grouped by message. Complements
            // the handler above; it bails out on records that have an exception,
            // so the two never report the same record twice.
            'sentry_issues' => [
                'type' => 'service',
                'id' => LogToSentryIssueHandler::class,
                'channels' => ['!deprecation'],
            ],
            // Existing handlers
            'main' => [
                'type' => 'fingers_crossed',
                'action_level' => 'error',
                'handler' => 'nested',
                'excluded_http_codes' => [404, 405],
                'channels' => ['!deprecation'],
                'buffer_size' => 50,
            ],
            'nested' => [
                'type' => 'stream',
                'path' => 'php://stderr',
                'level' => 'debug',
                'formatter' => 'monolog.formatter.json',
            ],
            'console' => [
                'type' => 'console',
                'process_psr_3_messages' => false,
                'channels' => ['!event', '!doctrine'],
            ],
            'deprecation' => [
                'type' => 'stream',
                'channels' => ['deprecation'],
                'path' => 'php://stderr',
                'formatter' => 'monolog.formatter.json',
            ],
        ],
    ],
]);
