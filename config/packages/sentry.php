<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Logging\DropInteractiveConsoleFailures;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

return App::config([
    'sentry' => [
        'dsn' => '%env(SENTRY_DSN)%',
        'register_error_listener' => false,
        'register_error_handler' => false,
        'options' => [
            'environment' => '%kernel.environment%',
            'send_default_pii' => true,
            // PII is deliberately on, so keep the whole request body too — the
            // default 'medium' silently drops bodies over ~10 kB (form posts,
            // webhook payloads) which is exactly the context we want on an issue.
            'max_request_body_size' => 'always',
            // Default 1024 clips serialized values with " {clipped}". Our log
            // context carries objects flattened by App\Logging\ObjectSerializer,
            // which easily exceeds that — raise so `monolog.context` stays whole.
            'max_value_length' => 4096,
            'ignore_exceptions' => [
                AccessDeniedException::class,
                MethodNotAllowedHttpException::class,
                NotFoundHttpException::class,
            ],
            // A hand-run maintenance command's failure is the operator's own
            // feedback, not an incident — see the service for why this is keyed
            // on the command and not on the exception class.
            'before_send' => DropInteractiveConsoleFailures::class,
            'max_breadcrumbs' => 50,
            'in_app_exclude' => ['%kernel.cache_dir%'],
            'in_app_include' => ['%kernel.project_dir%/src'],
            'traces_sample_rate' => 0,
            'profiles_sample_rate' => 0,
            'attach_stacktrace' => true,
            // Sentry Logs (structured logs product). Required by the Monolog
            // LogsHandler wired in config/packages/prod/monolog.php.
            'enable_logs' => true,
            // Logs are buffered until the request/command/message ends. Long
            // running processes (messenger worker, the cron commands) would hold
            // the whole batch in memory — flush once 100 records pile up.
            'log_flush_threshold' => 100,
        ],
        'messenger' => [
            'enabled' => true,
            // A failure that will still be retried (SMTP 421 from Seznam, a
            // transient DB hiccup) is weather, not fire — messenger already logs
            // it at WARNING, so it stays visible as a breadcrumb + Sentry Log.
            // Only the FINAL failure (retries exhausted, message headed to the
            // failed transport) becomes an issue.
            'capture_soft_fails' => false,
            // The worker is a long-running process: give every message its own
            // runtime context so scope/logs/metrics are flushed when it finishes
            // instead of leaking into the next message.
            'isolate_context_by_message' => true,
        ],
    ],
]);
