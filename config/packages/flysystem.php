<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

/*
 * User-uploaded files go through Flysystem, never through raw fopen/unlink — so a
 * later move off the box (S3/R2) is a config change, not a code change.
 *
 * `team_logos` is the only storage today: admin-uploaded team logos, served
 * straight off `public/uploads/teams` by Caddy. Entities store the STORAGE PATH
 * („019….webp"), never the URL — `public_url` is what turns one into the other,
 * so nothing in the database bakes in where the file physically lives.
 *
 * In production `public/uploads` is a persistent external volume (D34) shared by
 * web + worker, so deploys neither ship nor wipe these files.
 */
return App::config([
    'flysystem' => [
        'storages' => [
            'team_logos.storage' => [
                'adapter' => 'local',
                'options' => [
                    'directory' => '%kernel.project_dir%/public/uploads/teams',
                ],
                'public_url' => '/uploads/teams',
            ],
        ],
    ],
]);
