<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\HealthCheckController;
use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/**
 * The two probes behind `/-/health-check/*` mean different things and the difference is
 * the whole point (2026-09-03: six minutes of 500s with a green liveness probe):
 *
 *   liveness  — the process is up and Doctrine can reach the database. Doctrine
 *               reconnects on its own, so a Postgres restart does not turn it red.
 *   readiness — this worker can serve a page: the SESSION STORE is reachable through a
 *               connection opened the way a page request opens it. Red = 503, never 500.
 */
final class HealthCheckFlowTest extends WebTestCase
{
    public function testLivenessAnswersOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/-/health-check/liveness');

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok'], $this->json($client->getResponse()));
    }

    public function testReadinessExercisesTheSessionStore(): void
    {
        $client = static::createClient();
        $this->ensureSessionsTableExists();

        $client->request('GET', '/-/health-check/readiness');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['status' => 'ok', 'checks' => ['database' => 'ok', 'session_store' => 'ok']],
            $this->json($client->getResponse()),
        );
    }

    public function testReadinessAnswers503WhenTheSessionStoreIsUnreachable(): void
    {
        self::bootKernel();
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        // Doctrine is fine; the session store's connection is not (nothing listens on :1).
        $controller = new HealthCheckController(
            $connection,
            new NullLogger(),
            'pgsql:host=127.0.0.1;port=1;dbname=nope',
        );

        $response = $controller->readiness();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame(
            ['status' => 'unavailable', 'checks' => ['database' => 'ok'], 'error' => \PDOException::class],
            $this->json($response),
        );
    }

    /**
     * The test schema is built with `doctrine:schema:create` (entities only); the
     * `sessions` table comes from a plain-SQL migration in production. Create it the way
     * the handler itself would, on its own autocommitting connection, so the probe has
     * something to talk to. Idempotent: a second run trips "relation already exists".
     */
    private function ensureSessionsTableExists(): void
    {
        // .env.test → the wtips_test database, the same value the handler service resolves.
        $dsn = $_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? null;
        self::assertIsString($dsn);

        try {
            (new PdoSessionHandler($dsn))->createTable();
        } catch (\PDOException) {
            // already there
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        $content = $response->getContent();
        self::assertNotFalse($content);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
