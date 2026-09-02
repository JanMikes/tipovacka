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
            'exclude' => [
                // A parsed-input value object, not a service.
                '../src/Console/BulkTipWindowPlan.php',
            ],
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
        'App\\Logging\\' => [
            'resource' => '../src/Logging/',
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
                '../src/Service/Invitation/InvitationContext.php',
                '../src/Service/Invitation/InvitationContextStatus.php',
                '../src/Service/Payment/CheckoutSession.php',
                '../src/Service/Payment/CheckoutSessionDetails.php',
                '../src/Service/Payment/InvoiceDetails.php',
                '../src/Service/Payment/WebhookEvent.php',
                '../src/Service/Competition/GuessFeatures.php',
                '../src/Service/Competition/PendingJoin.php',
                '../src/Service/Scoring/MatchContext.php',
                '../src/Service/Feed/MatchSnapshot.php',
                '../src/Service/Feed/FeedSyncResult.php',
                '../src/Service/Feed/FeedSyncReport.php',
                '../src/Service/Feed/ExternalIdAdoption.php',
            ],
        ],
        // An organizer must hold no in-game advantage: they buy visibility boosts
        // like every other player. Flip to true to restore the pre-2026-07-23
        // behavior where managers/admins saw everyone's tips for free.
        'App\\Service\\Competition\\CompetitionEntitlements' => [
            'arguments' => [
                '$managersSeeTipsForFree' => false,
            ],
        ],
        // Other players' tips — concrete AND the anonymous 1 / X / 2 distribution —
        // become free to read only once a match HAS A FINAL RESULT, never merely
        // „past its tip deadline" (2026-07-30; a kickoff that passed without the
        // match being played must not reveal anything). Flip to false to restore the
        // deadline reveal on every surface at once; nothing else needs touching.
        'App\\Service\\Competition\\TipVisibilityGate' => [
            'arguments' => [
                '$freeRevealRequiresResult' => true,
            ],
        ],
        // „What would this player's tip deadline be if they OWNED the tip_change
        // boost?" — a SECOND EffectiveTipDeadlineResolver whose entitlement source
        // always says yes, so the „Počkejte si na sestavy" paywall can print the
        // exact moment the purchase hands back without re-deriving „kickoff minus
        // offset" anywhere. Everything else autowires, so the resolver may grow
        // dependencies without this definition rotting. Read ONLY through
        // App\Service\Competition\TipChangeUnlock.
        'app.tip_deadline_resolver.tip_change_granted' => [
            'class' => 'App\\Service\\EffectiveTipDeadlineResolver',
            'arguments' => [
                '$entitlements' => '@App\\Service\\Competition\\TipChangeGrantedEntitlements',
            ],
        ],
        'App\\Service\\Payment\\StripePaymentGateway' => [
            'arguments' => [
                '$secretKey' => '%env(STRIPE_SECRET_KEY)%',
            ],
        ],
        'App\\Service\\Payment\\StripeWebhookParser' => [
            'arguments' => [
                '$webhookSecret' => '%env(STRIPE_WEBHOOK_SECRET)%',
            ],
        ],
        'App\\Service\\Payment\\PaymentGateway' => [
            'alias' => 'App\\Service\\Payment\\StripePaymentGateway',
        ],
        'App\\Validator\\' => [
            'resource' => '../src/Validator/',
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
        'Symfony\\Component\\HttpFoundation\\Session\\Storage\\Handler\\PdoSessionHandler' => [
            // Sessions live in Postgres (table `sessions`, migration Version20260420194408).
            //
            // The handler gets the DSN — deliberately NOT Doctrine's native PDO object.
            // Given a DSN it opens its own connection lazily in open() and DROPS it in
            // close() ("only close lazy-connection"), i.e. it reconnects on every request.
            // Given a PDO object it keeps that one object for the lifetime of the
            // FrankenPHP worker: when Postgres was re-created on 2026-09-03, Doctrine
            // reconnected by itself but the handler kept the dead handle, and every page
            // failed with "SQLSTATE[HY000]: General error: 7 no connection to the server"
            // until the container was restarted — while the liveness probe (Doctrine)
            // stayed green. HealthCheckController::readiness probes this path.
            //
            // LOCK_ADVISORY stays: pg_advisory_lock per session for the request, released
            // in close(). The default LOCK_TRANSACTIONAL would hold a transaction open on
            // this second connection for the whole request; nothing here needs that.
            'arguments' => [
                '%env(resolve:DATABASE_URL)%',
                [
                    'lock_mode' => \Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler::LOCK_ADVISORY,
                ],
            ],
        ],
        'App\\Twig\\' => [
            'resource' => '../src/Twig/',
            'exclude' => ['../src/Twig/Components/'],
        ],
        'App\\Twig\\Components\\' => [
            'resource' => '../src/Twig/Components/',
        ],
        // Interpolates PSR-3 {placeholders} into the message for ALL handlers.
        // The Sentry handlers are 'type: service', which monolog-bundle does not
        // wrap with its per-handler process_psr_3_messages processor — without
        // this, Sentry issues literally read `running command "{command}"`.
        'Monolog\\Processor\\PsrLogMessageProcessor' => [
            'tags' => [['monolog.processor' => []]],
        ],
        // Sentry Monolog handlers. Sentry\Monolog\Handler (the old all-in-one) is
        // deprecated since sentry/sentry 4.24 and gone in 5.0; it is replaced by
        // the three single-purpose handlers below.
        //
        // INFO+ → Sentry Logs. Takes no hub — it writes into the Logs aggregator,
        // which the bundle flushes on kernel/console terminate and per messenger
        // message. Note it ignores Monolog processors registered ON the handler,
        // hence ours are registered on the logger (see PsrLogMessageProcessor above).
        'Sentry\\SentryBundle\\Monolog\\LogsHandler' => [
            'arguments' => [
                '$level' => \Monolog\Level::Info,
            ],
        ],
        // ERROR+ records carrying a Throwable → issue with stack trace.
        'Sentry\\Monolog\\ExceptionToSentryIssueHandler' => [
            'arguments' => [
                '$hub' => '@Sentry\\State\\HubInterface',
                '$level' => \Monolog\Level::Error,
            ],
        ],
        // ERROR+ records without a Throwable → message-grouped issue.
        'Sentry\\Monolog\\LogToSentryIssueHandler' => [
            'arguments' => [
                '$hub' => '@Sentry\\State\\HubInterface',
                '$level' => \Monolog\Level::Error,
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
