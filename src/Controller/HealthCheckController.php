<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/-/health-check', name: 'health_')]
final class HealthCheckController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        // The same DSN the session handler is built from (config/services.php), so the
        // readiness probe below opens a connection exactly the way a page request does.
        #[Autowire(env: 'resolve:DATABASE_URL')]
        private readonly string $sessionStoreDsn,
    ) {
    }

    /**
     * Liveness — "the process is up and Doctrine can reach the database".
     *
     * Doctrine reconnects on its own after a lost connection, so this stays green
     * across a Postgres restart. That makes it a liveness probe — fine for "should
     * this worker be killed", useless for "can it serve a page" (see readiness).
     */
    #[Route('/liveness', name: 'liveness', methods: ['GET'])]
    public function liveness(): JsonResponse
    {
        $this->connection->executeQuery('SELECT 1');

        return new JsonResponse(['status' => 'ok']);
    }

    /**
     * Readiness — "this worker can serve a page right now".
     *
     * Every page starts a session, so the probe exercises the session store the way
     * a request does: a fresh PdoSessionHandler on the same DSN opens its connection,
     * takes the advisory lock for a throw-away id, reads (a miss — the id never
     * exists, and in LOCK_ADVISORY mode a miss writes nothing) and closes. On
     * 2026-09-03 the liveness probe stayed 200 through six minutes of 500s because
     * Doctrine had reconnected while the session handler still held a dead PDO; this
     * probe would have answered 503. Failures are logged and answered 503, never 500.
     */
    #[Route('/readiness', name: 'readiness', methods: ['GET'])]
    public function readiness(): JsonResponse
    {
        $checks = [];

        try {
            $this->connection->executeQuery('SELECT 1');
            $checks['database'] = 'ok';

            $handler = new PdoSessionHandler($this->sessionStoreDsn, ['lock_mode' => PdoSessionHandler::LOCK_ADVISORY]);
            $handler->open('', 'readiness');

            try {
                $handler->read('readiness-'.bin2hex(random_bytes(8)));
            } finally {
                $handler->close();
            }

            $checks['session_store'] = 'ok';
        } catch (\Throwable $e) {
            $this->logger->error('Readiness check failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return new JsonResponse(
                ['status' => 'unavailable', 'checks' => $checks, 'error' => $e::class],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        return new JsonResponse(['status' => 'ok', 'checks' => $checks]);
    }
}
