<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'services' => [
        '_defaults' => [
            'autowire' => true,
            'autoconfigure' => true,
            'public' => true,
        ],
        '_instanceof' => [
            'App\\Rule\\Rule' => [
                'tags' => [['app.rule' => []]],
            ],
        ],
        'App\\Command\\' => [
            'resource' => '../src/Command/**/*Handler.php',
        ],
        'App\\Console\\' => [
            'resource' => '../src/Console/',
        ],
        'App\\Controller\\' => [
            'resource' => '../src/Controller/',
        ],
        'App\\Event\\' => [
            'resource' => '../src/Event/*{Handler,Subscriber}.php',
        ],
        'App\\Form\\' => [
            'resource' => '../src/Form/*FormType.php',
        ],
        'App\\Query\\' => [
            'resource' => '../src/Query/**/*Query.php',
        ],
        'App\\Query\\QueryBus' => null,
        'App\\Rule\\RuleRegistry' => null,
        'App\\Repository\\' => [
            'resource' => '../src/Repository/',
        ],
        'App\\Service\\' => [
            'resource' => '../src/Service/',
        ],
        'App\\Voter\\' => [
            'resource' => '../src/Voter/',
        ],
        'App\\Service\\Identity\\ProvideIdentity' => [
            'alias' => 'App\\Service\\Identity\\RandomIdentityProvider',
        ],
        'App\\Middleware\\DispatchDomainEventsMiddleware' => null,
        'App\\Twig\\' => [
            'resource' => '../src/Twig/',
            'exclude' => ['../src/Twig/Components/'],
        ],
        'App\\Twig\\Components\\' => [
            'resource' => '../src/Twig/Components/',
        ],
        // Sentry Monolog handlers
        'Sentry\\Monolog\\Handler' => [
            'arguments' => [
                '$hub' => '@Sentry\\State\\HubInterface',
                '$level' => \Monolog\Level::Error,
                '$bubble' => true,
                '$fillExtraContext' => true,
            ],
        ],
        'Sentry\\Monolog\\BreadcrumbHandler' => [
            'arguments' => [
                '$hub' => '@Sentry\\State\\HubInterface',
                '$level' => \Monolog\Level::Info,
            ],
        ],
    ],
]);
