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
                    'default_target_path' => 'dashboard',
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
        // Item 09 deleted the `/portal` URL prefix, so a path can no longer tell us who a
        // page is for: `/souteze` is the public discovery list, `/souteze/pozvanka/{token}`
        // is a logged-out invitation landing, and `/souteze/{id}` is the members-only hub —
        // one prefix, three audiences. The authenticated boundary therefore moved into the
        // code, as `#[IsGranted('ROLE_USER')]` on every controller in `src/Controller/Portal`
        // (enforced route-by-route by tests/Integration/Security/AnonymousReachabilityTest).
        //
        // `^/admin` stays a path rule: it is a genuine, stable audience boundary and the one
        // prefix a URL can still speak for.
        'access_control' => [
            ['path' => '^/admin', 'roles' => 'ROLE_ADMIN'],
        ],
        'role_hierarchy' => [
            'ROLE_ADMIN' => ['ROLE_USER'],
        ],
    ],
]);
