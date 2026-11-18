<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\SportMatch;

use App\Service\SportMatch\MissingResult;
use App\Service\SportMatch\MissingResultsCheck;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The daily safety net: a played match of a curated zdroj without a result is
 * an incident (admins mailed, command fails) whatever its zdroj — so a user is
 * never the first to notice. MATCH_LIVE (curated PUBLIC source) kicked off
 * 2025-06-15 11:00, one hour before the frozen clock.
 */
final class MissingResultsCheckTest extends IntegrationTestCase
{
    public function testAFreshlyPlayedMatchIsNotMissingYet(): void
    {
        $report = $this->check()->run();

        self::assertNotContains('Viktoria Plzeň – Baník Ostrava', $this->matchNames($report->curated));
    }

    public function testAMatchStillWithoutAResultADayLaterIsReportedAndMailedToAdmins(): void
    {
        $clock = $this->clock();
        self::assertInstanceOf(MockClock::class, $clock);
        $clock->modify('+1 day');

        $report = $this->check()->run();

        self::assertContains('Viktoria Plzeň – Baník Ostrava', $this->matchNames($report->curated));
        self::assertGreaterThanOrEqual(1, [] === self::getMailerMessages() ? count($this->messengerAsyncTransport()->getSent()) : count(self::getMailerMessages()));
    }

    public function testDryRunNeitherLogsNorMails(): void
    {
        $clock = $this->clock();
        self::assertInstanceOf(MockClock::class, $clock);
        $clock->modify('+1 day');

        $report = $this->check()->run(notify: false);

        self::assertNotSame([], $report->curated);
        self::assertSame([], self::getMailerMessages());
    }

    /**
     * @param list<MissingResult> $missing
     *
     * @return list<string>
     */
    private function matchNames(array $missing): array
    {
        return array_map(static fn (MissingResult $result): string => $result->homeTeam.' – '.$result->awayTeam, $missing);
    }

    private function check(): MissingResultsCheck
    {
        return self::getContainer()->get(MissingResultsCheck::class);
    }
}
