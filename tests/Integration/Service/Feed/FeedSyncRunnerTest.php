<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Feed;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Enum\FeedProvider;
use App\Enum\SportMatchState;
use App\Exception\FeedResultsOverdue;
use App\Repository\MatchSourceRepository;
use App\Repository\SportMatchRepository;
use App\Service\Feed\FeedPollPolicy;
use App\Service\Feed\FeedSynchronizer;
use App\Service\Feed\FeedSyncReport;
use App\Service\Feed\FeedSyncRunner;
use App\Service\Feed\MatchDataProviderRegistry;
use App\Tests\Support\IntegrationTestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

/**
 * The feed pipeline's answer to the 2026-10-28 → 11-17 Premier League outage:
 * the vendor answered „no fixtures" for three weeks, every pass reported
 * success, and 20 played matches were never finished nor evaluated.
 *
 * Drives the real runner over the fixture-file provider: a match is created by
 * the feed, then the feed stops delivering its result — the pass must FAIL with
 * the match listed as overdue, keep the source warm, and recover by itself the
 * moment the result arrives.
 */
final class FeedSyncRunnerTest extends IntegrationTestCase
{
    private const string EXTERNAL_ID = 'heal-1';

    private string $feedFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->feedFile = sprintf('%s/feed-runner-%s.json', sys_get_temp_dir(), bin2hex(random_bytes(4)));
        $this->writeFeed('scheduled');

        $source = $this->source();
        $source->bindFeed(FeedProvider::Fixture, $this->feedFile, $this->clock()->now());
        $this->entityManager()->flush();
    }

    protected function tearDown(): void
    {
        @unlink($this->feedFile);

        parent::tearDown();
    }

    public function testAResultThatNeverArrivesFailsThePassUntilItDoes(): void
    {
        // 2025-06-15 12:00 — the match kicks off at 14:00, so the feed creates it.
        $created = $this->sync();
        self::assertSame([], $created->overdue, 'fixture matches carry no externalId — the feed owes them nothing');
        self::assertFalse($created->hasFailures);
        self::assertSame(SportMatchState::Scheduled, $this->syncedMatch()?->state);

        // A day later the feed still calls it scheduled: the outage.
        $this->mockClock()->modify('+1 day');
        $outage = $this->sync();

        self::assertCount(1, $outage->overdue);
        self::assertStringContainsString('Sparta Praha – Slavia Praha (2025-06-15 14:00 UTC)', $outage->overdue[0]);
        self::assertTrue($outage->hasFailures, 'an undelivered result is an incident, not a warning');

        // The result finally arrives: the next pass finishes the match and is green.
        $this->writeFeed('finished', 2, 1);
        $healed = $this->sync();

        self::assertSame([], $healed->overdue);
        self::assertFalse($healed->hasFailures);
        self::assertSame(SportMatchState::Finished, $this->syncedMatch()?->state);
    }

    /**
     * „We must not hear about it from a user": once an hour the overdue state is
     * an ERROR carrying {@see FeedResultsOverdue} — its own Sentry issue, which
     * the „wtips feed" alert rule mails on every occurrence. The other passes of
     * the hour stay at warning so an outage costs ~24 Sentry events a day.
     */
    public function testOverdueResultsRaiseOneDedicatedErrorAnHour(): void
    {
        $this->sync();
        $this->mockClock()->modify('+1 day -2 minutes'); // 2025-06-16 11:58

        $logger = $this->recordingLogger();
        $this->runnerLoggingTo($logger)->run(onlySourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertSame(['warning'], array_column($logger->records, 'level'), 'mid-hour: warning only');

        $this->mockClock()->modify('+3 minutes'); // 12:01 — the top-of-the-hour pass
        $logger = $this->recordingLogger();
        $this->runnerLoggingTo($logger)->run(onlySourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));

        self::assertSame(['error'], array_column($logger->records, 'level'));
        $exception = $logger->records[0]['context']['exception'] ?? null;
        self::assertInstanceOf(FeedResultsOverdue::class, $exception);
        self::assertStringContainsString('1 played match has still without a result', $exception->getMessage());
    }

    public function testOrdinaryResultLatencyIsNotOverdue(): void
    {
        $this->sync();

        // Four hours after kickoff — a slow feed, not a dead one.
        $this->mockClock()->modify('+6 hours');

        self::assertSame([], $this->sync()->overdue);
    }

    /**
     * What the self-healing window is told to reach back to, and the cadence
     * that makes it catch up within half an hour of the feed coming back.
     */
    public function testAMatchAwaitingItsResultIsTrackedAndKeepsTheSourceWarm(): void
    {
        $policy = self::getContainer()->get(FeedPollPolicy::class);
        $later = new \DateTimeImmutable('2025-06-17 12:00:00 UTC');

        self::assertSame('cold', $policy->cadence($this->source(), $later), 'nothing near before the feed creates its match');

        $this->sync();

        self::assertEquals(
            new \DateTimeImmutable('2025-06-15 14:00:00 UTC'),
            $this->sportMatches()->findOldestKickoffAwaitingFeedResult(
                Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
                new \DateTimeImmutable('2025-01-01 00:00:00 UTC'),
                $later,
            ),
        );
        self::assertSame('warm', $policy->cadence($this->source(), $later));
        self::assertSame(
            'cold',
            $policy->cadence($this->source(), new \DateTimeImmutable('2025-06-25 06:00:00 UTC')),
            'a result the feed never delivers stops holding the source warm after a week',
        );
    }

    private function sync(): FeedSyncReport
    {
        $runner = self::getContainer()->get(FeedSyncRunner::class);

        $report = $runner->run(onlySourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertSame([], $report->failed);

        return $report;
    }

    private function runnerLoggingTo(LoggerInterface $logger): FeedSyncRunner
    {
        $container = self::getContainer();

        return new FeedSyncRunner(
            $container->get(MatchSourceRepository::class),
            $container->get(SportMatchRepository::class),
            $container->get(MatchDataProviderRegistry::class),
            $container->get(FeedSynchronizer::class),
            $container->get(FeedPollPolicy::class),
            $this->clock(),
            $logger,
            $this->commandBus(),
        );
    }

    /**
     * @return AbstractLogger&object{records: list<array{level: mixed, message: string|\Stringable, context: array<mixed>}>}
     */
    private function recordingLogger(): AbstractLogger
    {
        return new class () extends AbstractLogger {
            /** @var list<array{level: mixed, message: string|\Stringable, context: array<mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }
        };
    }

    private function writeFeed(string $status, ?int $homeScore = null, ?int $awayScore = null): void
    {
        $row = [
            'externalId' => self::EXTERNAL_ID,
            'homeTeam' => 'Sparta Praha',
            'awayTeam' => 'Slavia Praha',
            'kickoffUtc' => '2025-06-15T14:00:00Z',
            'status' => $status,
        ];

        if (null !== $homeScore && null !== $awayScore) {
            $row['homeScore'] = $homeScore;
            $row['awayScore'] = $awayScore;
        }

        file_put_contents($this->feedFile, json_encode([$row], JSON_THROW_ON_ERROR));
    }

    private function syncedMatch(): ?\App\Entity\SportMatch
    {
        return $this->sportMatches()->findBySourceAndExternalId(Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID), self::EXTERNAL_ID);
    }

    private function source(): MatchSource
    {
        $source = $this->entityManager()->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $source);

        return $source;
    }

    private function sportMatches(): SportMatchRepository
    {
        $repository = self::getContainer()->get(SportMatchRepository::class);

        return $repository;
    }

    private function mockClock(): MockClock
    {
        $clock = $this->clock();
        self::assertInstanceOf(MockClock::class, $clock);

        return $clock;
    }
}
