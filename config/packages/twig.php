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
        // Brand name is config-driven for dual deployment: main -> wtips.cz ("Wtips"),
        // the `tipovacka` branch can override APP_BRAND_NAME to keep "Tipovačka".
        'globals' => [
            'brand_name' => '%env(APP_BRAND_NAME)%',
            // Visual-only premium teaser flag (no commerce backend yet). Off by default.
            'premium_enabled' => '%env(bool:APP_PREMIUM_TEASER_ENABLED)%',
        ],
    ],
]);
