<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'monolog' => [
        'handlers' => [
            // In-memory Monolog\Handler\TestHandler (service monolog.handler.capture)
            // so tests can assert WHAT got logged at WHICH level — e.g. that an
            // expected verify-link rejection stays below ERROR (Sentry-issue bar).
            'capture' => [
                'type' => 'test',
                'level' => 'debug',
                'channels' => ['!event'],
            ],
            'main' => [
                'type' => 'fingers_crossed',
                'action_level' => 'error',
                'handler' => 'nested',
                'excluded_http_codes' => [404, 405],
                'channels' => ['!event'],
            ],
            'nested' => [
                'type' => 'stream',
                'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                'level' => 'debug',
            ],
        ],
    ],
]);
