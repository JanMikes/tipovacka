<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Feed;

use App\Enum\FeedMatchStatus;
use App\Enum\FeedProvider;
use App\Exception\FeedPayloadInvalid;
use App\Service\Feed\FacrMatchDataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Locks the shape of the FAČR soutěž page we scrape. The HTML below is a
 * trimmed copy of a real `detail-souteze.aspx` response (MSFL / SATUM 5. liga,
 * fetched 2026-08-08) — same cell order, same score spellings, same zápis link
 * texts. If IS FAČR ever changes any of those, this test is where it shows.
 */
final class FacrMatchDataProviderTest extends TestCase
{
    public function testParsesFixtureRowsIntoSnapshots(): void
    {
        $snapshots = $this->provider($this->page())->fetchMatches(FeedSourceFactory::create(FeedProvider::Facr, 'guid-1'));

        self::assertCount(4, $snapshots);

        $played = $snapshots[0];
        self::assertSame('4adbd087-d65b-4859-bcfe-4312dec1fc3b', $played->externalId);
        self::assertSame('Český Těšín', $played->homeTeamName, 'the (16) table rank is not part of the name');
        self::assertSame('FC Vřesina', $played->awayTeamName);
        // 08.08.2026 10:30 Prague (CEST, +02:00) is 08:30 UTC.
        self::assertSame('2026-08-08 08:30:00', $played->kickoffUtc->format('Y-m-d H:i:s'));
        self::assertSame(FeedMatchStatus::Finished, $played->status);
        self::assertSame(4, $played->homeScore);
        self::assertSame(1, $played->awayScore);
        self::assertSame('Český Těšín - tráva', $played->venue);
        self::assertNull($played->events, 'FAČR is score-only — a null sheet must never wipe manual scorers');
    }

    public function testUnplayedFixtureIsScheduledWithoutScore(): void
    {
        $snapshots = $this->provider($this->page())->fetchMatches(FeedSourceFactory::create(FeedProvider::Facr, 'guid-1'));

        $scheduled = $snapshots[1];
        self::assertSame(FeedMatchStatus::Scheduled, $scheduled->status);
        self::assertNull($scheduled->homeScore);
        self::assertNull($scheduled->awayScore);
    }

    public function testClosedReportIsAlsoFinished(): void
    {
        $snapshots = $this->provider($this->page())->fetchMatches(FeedSourceFactory::create(FeedProvider::Facr, 'guid-1'));

        self::assertSame(FeedMatchStatus::Finished, $snapshots[2]->status);
        self::assertSame(2, $snapshots[2]->homeScore);
        self::assertSame(2, $snapshots[2]->awayScore);
    }

    /**
     * The combination we have never seen (a score with no zápis state) must not
     * be guessed — the synchronizer reports Unknown and skips the row.
     */
    public function testUnrecognizedCombinationBecomesUnknown(): void
    {
        $snapshots = $this->provider($this->page())->fetchMatches(FeedSourceFactory::create(FeedProvider::Facr, 'guid-1'));

        self::assertSame(FeedMatchStatus::Unknown, $snapshots[3]->status);
        self::assertNotNull($snapshots[3]->rawStatus);
    }

    public function testPageWithoutFixturesFailsLoudly(): void
    {
        $this->expectException(FeedPayloadInvalid::class);

        $this->provider('<html><body><p>Soutěž nenalezena</p></body></html>')
            ->fetchMatches(FeedSourceFactory::create(FeedProvider::Facr, 'wrong-guid'));
    }

    public function testMissingFeedRefFailsBeforeAnyRequest(): void
    {
        $this->expectException(FeedPayloadInvalid::class);

        $this->provider($this->page())->fetchMatches(FeedSourceFactory::create(FeedProvider::Facr, ' '));
    }

    private function provider(string $html): FacrMatchDataProvider
    {
        return new FacrMatchDataProvider(new MockHttpClient(new MockResponse($html, [
            'response_headers' => ['content-type' => 'text/html; charset=utf-8'],
        ])));
    }

    private function page(): string
    {
        return <<<'HTML'
            <html><body><table>
              <tr><th>datum</th><th>domácí</th><th>hosté</th><th>skóre</th><th>hřiště</th><th>pzn.</th><th>akce</th></tr>
              <tr class="type_false">
                <td>08.08.2026 10:30</td>
                <td>Český Těšín <i>(16)</i></td>
                <td>FC Vřesina <i>(9)</i></td>
                <td>4 : 1 <span class='penalta-ne'> (PK:0:0) </span></td>
                <td>Český Těšín - tráva</td>
                <td></td>
                <td><a href="../zapasy/zapis-o-utkani-report.aspx?zapas=4adbd087-d65b-4859-bcfe-4312dec1fc3b&amp;zapis=1">zápis neuzavřen</a><a href="../zapasy/zapas-delegace-report.aspx?zapas=4adbd087-d65b-4859-bcfe-4312dec1fc3b">delegace</a></td>
              </tr>
              <tr class="type_false">
                <td>09.08.2026 10:15</td>
                <td>Sigma Olomouc B <i>(18)</i></td>
                <td>SLOVÁCKO B <i>(10)</i></td>
                <td>-- : -- <span class='penalta-ne'> (PK:0:0) </span></td>
                <td>Andrův stadion, hřiště č. 2</td>
                <td>Původní termín: 09.08.2026 10:15</td>
                <td><a href="../zapasy/zapis-o-utkani-report.aspx?zapas=11111111-2222-3333-4444-555555555555&amp;zapis=1">nezahájen</a></td>
              </tr>
              <tr class="type_false">
                <td>08.08.2026 17:00</td>
                <td>H&amp;P Staré Město <i>(14)</i></td>
                <td>Heřmanice <i>(4)</i></td>
                <td>2 : 2 <span class='penalta-ne'> (PK:0:0) </span></td>
                <td>Staré Město - tráva</td>
                <td></td>
                <td><a href="../zapasy/zapis-o-utkani-report.aspx?zapas=aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee&amp;zapis=1">zápis uzavřen</a></td>
              </tr>
              <tr class="type_false">
                <td>10.08.2026 17:00</td>
                <td>Hlubina <i>(1)</i></td>
                <td>Hranice <i>(2)</i></td>
                <td>3 : 0 <span class='penalta-ne'> (PK:0:0) </span></td>
                <td>Hlubina - tráva</td>
                <td>kontumace</td>
                <td><a href="../zapasy/zapas-delegace-report.aspx?zapas=99999999-8888-7777-6666-555555555555">delegace</a></td>
              </tr>
            </table></body></html>
            HTML;
    }
}
