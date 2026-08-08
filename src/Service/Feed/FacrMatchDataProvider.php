<?php

declare(strict_types=1);

namespace App\Service\Feed;

use App\Entity\MatchSource;
use App\Enum\FeedMatchStatus;
use App\Enum\FeedProvider;
use App\Exception\FeedPayloadInvalid;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * FAČR IS (is.fotbal.cz) — the free system of record for every Czech soutěž.
 *
 * `feedRef` is the soutěž detail GUID, and ONE anonymous GET returns the whole
 * season (320–830 kB of HTML):
 *
 *   /public/souteze/detail-souteze.aspx?req=<GUID>&sport=fotbal
 *
 * No login, no cookies, no ViewState. (The club-scoped Excel export documented
 * elsewhere needs all three — we deliberately do not use it: the soutěž page
 * carries every match of the competition, not just one club's.)
 *
 * Each fixture is a `<tr class="type_*">` of seven cells:
 *   0 kickoff „08.08.2026 10:30" (Europe/Prague) · 1 home + table rank ·
 *   2 away + rank · 3 „4 : 1 (PK:0:0)" or „-- : -- (PK:0:0)" · 4 venue ·
 *   5 poznámka · 6 actions, carrying `zapas=<GUID>` — our externalId.
 *
 * SCORE-ONLY provider: the zápis o utkání (the one place goal scorers live) is
 * behind the login, so snapshots carry `events: null` and manually entered
 * scorers are never overwritten. See FeedProvider::reportsScorers().
 */
final readonly class FacrMatchDataProvider implements MatchDataProvider
{
    private const string BASE_URL = 'https://is.fotbal.cz/public/souteze/detail-souteze.aspx';

    /** FAČR renders every date in Prague wall-clock time; we store UTC. */
    private const string SOURCE_TIMEZONE = 'Europe/Prague';

    private const int TIMEOUT_SECONDS = 30;

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public static function provides(): FeedProvider
    {
        return FeedProvider::Facr;
    }

    public function fetchMatches(MatchSource $source): array
    {
        $html = $this->fetchCompetitionPage((string) $source->feedRef);

        $rows = (new Crawler($html))->filter('tr[class^="type_"]');

        if (0 === $rows->count()) {
            // A wrong GUID renders a valid page with no fixture table at all —
            // failing loudly beats silently reporting "0 matches, all unchanged".
            throw FeedPayloadInvalid::emptyResponse(self::provides()->label(), (string) $source->feedRef);
        }

        $snapshots = [];

        foreach ($rows as $index => $row) {
            $snapshot = $this->snapshotFromRow($index, new Crawler($row));

            if ($snapshot instanceof MatchSnapshot) {
                $snapshots[] = $snapshot;
            }
        }

        return $snapshots;
    }

    private function fetchCompetitionPage(string $competitionGuid): string
    {
        if ('' === trim($competitionGuid)) {
            throw FeedPayloadInvalid::missingFeedRef(self::provides()->label(), 'soutěž detail GUID');
        }

        try {
            return $this->httpClient->request('GET', self::BASE_URL, [
                'query' => ['req' => $competitionGuid, 'sport' => 'fotbal'],
                'timeout' => self::TIMEOUT_SECONDS,
                // The IS serves a different (mobile) layout to unknown agents.
                'headers' => ['Accept' => 'text/html'],
            ])->getContent();
        } catch (HttpExceptionInterface $e) {
            throw FeedPayloadInvalid::requestFailed(self::provides()->label(), $e->getMessage());
        }
    }

    /**
     * Null when the row is not a fixture row (the table also carries spacer and
     * heading rows that share the `type_*` class).
     */
    private function snapshotFromRow(int $index, Crawler $row): ?MatchSnapshot
    {
        $cells = $row->filter('td');

        if ($cells->count() < 7) {
            return null;
        }

        $externalId = $this->externalId($row);

        if (null === $externalId) {
            return null;
        }

        $kickoff = $this->kickoff($index, $this->text($cells->eq(0)));
        $homeTeam = $this->teamName($this->text($cells->eq(1)));
        $awayTeam = $this->teamName($this->text($cells->eq(2)));

        if ('' === $homeTeam || '' === $awayTeam) {
            throw FeedPayloadInvalid::invalidRow($index, 'missing home/away team');
        }

        $scoreCell = $this->text($cells->eq(3));
        $actionsCell = $this->text($cells->eq(6));
        [$homeScore, $awayScore] = $this->score($scoreCell);
        $venue = $this->text($cells->eq(4));

        return new MatchSnapshot(
            externalId: $externalId,
            homeTeamName: $homeTeam,
            awayTeamName: $awayTeam,
            kickoffUtc: $kickoff,
            status: $this->status($homeScore, $actionsCell),
            homeScore: $homeScore,
            awayScore: $awayScore,
            // Score-only provider: never claim knowledge of the event sheet.
            events: null,
            venue: '' !== $venue ? $venue : null,
            rawStatus: sprintf('%s | %s', $scoreCell, $actionsCell),
        );
    }

    /**
     * The zápas GUID from any `…?zapas=<GUID>` link in the row's action cell —
     * the same identifier the seed JSONs already store as `externalId`.
     */
    private function externalId(Crawler $row): ?string
    {
        /** @var list<string> $hrefs */
        $hrefs = $row->filter('a')->extract(['href']);

        foreach ($hrefs as $href) {
            if (1 === preg_match('/[?&]zapas=([0-9a-f-]{36})/i', $href, $matches)) {
                return strtolower($matches[1]);
            }
        }

        return null;
    }

    /**
     * Status is not a column — it is derived from two observed signals, and any
     * combination we have NOT seen becomes Unknown so the synchronizer reports
     * it instead of guessing (kontumace and odložený zápas will surface here
     * first and each is then one line in this table).
     */
    private function status(?int $homeScore, string $actionsCell): FeedMatchStatus
    {
        $hasScore = null !== $homeScore;
        $zapisState = $this->zapisState($actionsCell);

        return match (true) {
            // „-- : --" + „nezahájen" — the ordinary future fixture.
            !$hasScore && 'nezahájen' === $zapisState => FeedMatchStatus::Scheduled,
            // A score exists: the referee filed the result. „neuzavřen" only
            // means the report is still open for edits — the result is real and
            // a later correction re-evaluates cleanly (FeedSyncResult::corrected).
            $hasScore && in_array($zapisState, ['zápis uzavřen', 'zápis neuzavřen'], true) => FeedMatchStatus::Finished,
            default => FeedMatchStatus::Unknown,
        };
    }

    private function zapisState(string $actionsCell): string
    {
        foreach (['zápis uzavřen', 'zápis neuzavřen', 'nezahájen'] as $known) {
            if (str_contains($actionsCell, $known)) {
                return $known;
            }
        }

        return '';
    }

    private function kickoff(int $index, string $raw): \DateTimeImmutable
    {
        // The leading „!" resets every field the format does not parse, so the
        // seconds come out 0 instead of inheriting the current wall clock.
        $kickoff = \DateTimeImmutable::createFromFormat(
            '!d.m.Y H:i',
            $raw,
            new \DateTimeZone(self::SOURCE_TIMEZONE),
        );

        if (false === $kickoff) {
            throw FeedPayloadInvalid::invalidRow($index, sprintf('unparseable kickoff "%s"', $raw));
        }

        return $kickoff->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * „4 : 1 (PK:0:0)" → [4, 1]; „-- : -- (PK:0:0)" → [null, null].
     * The (PK:x:y) suffix is the penalty shootout; it is deliberately ignored
     * here — see the AET/pens note in .docs/MATCH_DATA_FEEDS.md. Amateur CZ
     * soutěže play no shootouts, so it is always 0:0 in practice.
     *
     * @return array{int|null, int|null}
     */
    private function score(string $cell): array
    {
        if (1 !== preg_match('/^(\d+)\s*:\s*(\d+)/', $cell, $matches)) {
            return [null, null];
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    /** „Český Těšín (16)" → „Český Těšín" — the parenthesised number is the table rank. */
    private function teamName(string $cell): string
    {
        return trim((string) preg_replace('/\s*\(\d+\)\s*$/u', '', $cell));
    }

    private function text(Crawler $cell): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $cell->text('')));
    }
}
