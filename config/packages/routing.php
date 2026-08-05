<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'router' => [
            // Base URI for URLs generated outside an HTTP request (cron commands,
            // messenger workers). MUST be the canonical public origin — `APP_URL`
            // is the one name the deployment sets (compose.yaml on the box); a
            // second name here silently falls back to the dev default and ships
            // localhost links in notifications and e-mails.
            'default_uri' => '%env(APP_URL)%',
        ],
    ],
]);
