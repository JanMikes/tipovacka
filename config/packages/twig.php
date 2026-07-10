<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'twig' => [
        'file_name_pattern' => '*.twig',
        'form_themes' => [
            'form/_form_theme.html.twig',
        ],
        'date' => [
            'timezone' => 'Europe/Prague',
        ],
        'globals' => [
            // Brand name. Hardcoded "Wtips" — the app is fully rebranded, no transition phase.
            'brand_name' => 'Wtips',
            // Visual-only premium teaser flag (no commerce backend yet). Off by default.
            // `default::` makes the var optional: if it is unset in any environment the
            // flag degrades to false instead of throwing EnvNotFoundException — which would
            // otherwise 500 every page (Twig globals) and every Doctrine flush (Turbo listener).
            'premium_enabled' => '%env(bool:default::APP_PREMIUM_TEASER_ENABLED)%',
            // Base URL for "open in Stripe dashboard" admin links — points at
            // /test in sandbox environments, the bare dashboard in production.
            'stripe_dashboard_url' => '%env(default::STRIPE_DASHBOARD_URL)%',
        ],
    ],
]);
