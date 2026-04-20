<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Entity\User;
use App\Service\Security\AppUserChecker;

return App::config([
    'security' => [
        'password_hashers' => [
            'Symfony\\Component\\Security\\Core\\User\\PasswordAuthenticatedUserInterface' => 'auto',
        ],
        'providers' => [
            'app_user_provider' => [
                'entity' => [
                    'class' => User::class,
                    'property' => 'email',
                ],
            ],
        ],
        'firewalls' => [
            'dev' => [
                'pattern' => '^/(_(profiler|wdt)|css|images|js)/',
                'security' => false,
            ],
            'main' => [
                'lazy' => true,
                'provider' => 'app_user_provider',
                'user_checker' => AppUserChecker::class,
                'form_login' => [
                    'login_path' => 'app_login',
                    'check_path' => 'app_login',
                    'enable_csrf' => false,
                    'default_target_path' => 'portal_dashboard',
                ],
                'logout' => [
                    'path' => 'app_logout',
                    'target' => 'app_login',
                ],
                'remember_me' => [
                    'secret' => '%kernel.secret%',
                    'lifetime' => 604800, // 7 days
                    'path' => '/',
                    'always_remember_me' => false,
                ],
                'switch_user' => [
                    'role' => 'ROLE_ADMIN',
                    'parameter' => '_switch_user',
                ],
            ],
        ],
        'access_control' => [
            ['path' => '^/-/health-check/liveness', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/skupiny/pozvanka', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/pozvanka', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/nastenka', 'roles' => 'ROLE_USER'],
            ['path' => '^/portal', 'roles' => 'ROLE_USER'],
            ['path' => '^/pripojit', 'roles' => 'ROLE_USER'],
            ['path' => '^/prihlaseni', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/registrace', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/reset-hesla', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/overit-email', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/overeni-ceka', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/odhlaseni', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/admin', 'roles' => 'ROLE_ADMIN'],
        ],
        'role_hierarchy' => [
            'ROLE_ADMIN' => ['ROLE_USER'],
        ],
    ],
]);
