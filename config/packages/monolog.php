<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'monolog' => [
        // `signup`: why a registration form was rejected (App\Service\SignUp\RejectedSignUpLog).
        'channels' => ['deprecation', 'signup'],
    ],
]);
