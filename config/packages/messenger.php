<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'messenger' => [
            'failure_transport' => 'failed',
            'transports' => [
                'async' => [
                    'dsn' => '%env(MESSENGER_TRANSPORT_DSN)%',
                    'options' => [
                        'use_notify' => true,
                        'check_delayed_interval' => 60000,
                    ],
                    // 1 min → 4 min → 16 min → 30 min (capped), ±10 % jitter:
                    // long enough to outlive SMTP greylisting/timeouts (the
                    // Seznam 421s), and the doctrine transport only polls for
                    // delayed messages every check_delayed_interval anyway, so
                    // sub-minute delays would be rounded up to ~60 s regardless.
                    'retry_strategy' => [
                        'max_retries' => 4,
                        'delay' => 60000,
                        'multiplier' => 4,
                        'max_delay' => 1800000,
                    ],
                ],
                'failed' => 'doctrine://default?queue_name=failed',
            ],
            'default_bus' => 'command.bus',
            'buses' => [
                'command.bus' => [
                    'middleware' => [
                        'App\\Middleware\\DispatchDomainEventsMiddleware',
                        'doctrine_transaction',
                        'validation',
                    ],
                ],
                'query.bus' => [
                    'middleware' => [
                        'validation',
                    ],
                ],
                'event.bus' => [
                    'default_middleware' => [
                        'enabled' => true,
                        'allow_no_handlers' => true,
                    ],
                    'middleware' => [
                        'App\\Middleware\\DispatchDomainEventsMiddleware',
                        'doctrine_transaction',
                        'validation',
                    ],
                ],
            ],
            'routing' => [
                'Symfony\\Component\\Mailer\\Messenger\\SendEmailMessage' => 'async',
                'Symfony\\Component\\Notifier\\Message\\ChatMessage' => 'async',
                'Symfony\\Component\\Notifier\\Message\\SmsMessage' => 'async',
                'App\\Command\\RecalculateCompetitionPoints\\RecalculateCompetitionPointsCommand' => 'async',
                'App\\Command\\SettleUncoveredPremiumCharges\\SettleUncoveredPremiumChargesCommand' => 'async',
                'App\\Command\\CaptureLeaderboardSnapshots\\CaptureLeaderboardSnapshotsCommand' => 'async',
            ],
        ],
    ],
]);
