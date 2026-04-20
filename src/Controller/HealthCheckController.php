<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/-/health-check/liveness', name: 'health_liveness', methods: ['GET'])]
final class HealthCheckController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $this->connection->executeQuery('SELECT 1');

        return new JsonResponse(['status' => 'ok']);
    }
}
