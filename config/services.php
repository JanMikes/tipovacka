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
        'App\\Rule\\' => [
            'resource' => '../src/Rule/',
            'exclude' => [
                '../src/Rule/AsRule.php',
                '../src/Rule/Rule.php',
                '../src/Rule/RuleRegistry.php',
            ],
        ],
        'App\\Rule\\RuleRegistry' => null,
        'App\\Repository\\' => [
            'resource' => '../src/Repository/',
        ],
        'App\\Service\\' => [
            'resource' => '../src/Service/',
            'exclude' => [
                '../src/Service/SportMatch/SportMatchImportRow.php',
                '../src/Service/SportMatch/SportMatchImportError.php',
                '../src/Service/SportMatch/SportMatchImportPreview.php',
            ],
        ],
        'App\\Voter\\' => [
            'resource' => '../src/Voter/',
            'exclude' => [
                '../src/Voter/GuessVotingContext.php',
                '../src/Voter/GuessOnBehalfContext.php',
            ],
        ],
        'App\\Service\\Identity\\ProvideIdentity' => [
            'alias' => 'App\\Service\\Identity\\RandomIdentityProvider',
        ],
        'App\\Middleware\\DispatchDomainEventsMiddleware' => null,
        'session.pdo' => [
            'class' => \PDO::class,
            'factory' => ['@doctrine.dbal.default_connection', 'getNativeConnection'],
        ],
        'Symfony\\Component\\HttpFoundation\\Session\\Storage\\Handler\\PdoSessionHandler' => [
            'arguments' => [
                '@session.pdo',
            ],
        ],
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
