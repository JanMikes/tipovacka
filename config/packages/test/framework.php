<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'test' => true,
        'session' => [
            'storage_factory_id' => 'session.storage.factory.mock_file',
        ],
        'rate_limiter' => [
            // In-memory storage so limiter state never leaks between test runs
            'sign_up_invitation' => [
                'storage_service' => \Symfony\Component\RateLimiter\Storage\InMemoryStorage::class,
            ],
        ],
    ],
]);
