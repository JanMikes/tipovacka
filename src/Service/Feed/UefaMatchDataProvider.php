<?php

declare(strict_types=1);

namespace App\Service\Feed;

use App\Entity\MatchSource;
use App\Enum\FeedMatchStatus;
use App\Enum\FeedProvider;
use App\Enum\MatchEventType;
use App\Enum\MatchSide;
use App\Exception\FeedPayloadInvalid;
use App\Value\MatchEventInput;
use App\Value\OvertimeOutcome;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * UEFA's own open API — the free system of record for Liga mistrů (competitionId
 * 1), Evropská liga (14) and Konferenční liga (2019), which no affordable vendor
 * plan covers. `feedRef` is that competitionId.
 *
 *   GET match.uefa.com/v5/matches?competitionId=<ref>&seasonYear=<year>&limit=&offset=0
 *
 * `offset` is mandatory — omitting it answers 404 „null is not valid for offset".
 * The endpoint returns a plain JSON array (no envelope, no pagination metadata),
 * so we page by offset until a short page comes back.
 *
 * A FULL provider: finished matches carry `score.regular` / `score.total` /
 * `score.penalty` and a `playerEvents.scorers[]` array, and the `id` is
 * byte-identical to the externalId already stored for these competitions.
 */
final readonly class UefaMatchDataProvider implements MatchDataProvider
{
    private const string BASE_URL = 'https://match.uefa.com/v5/matches';

    private const int PAGE_SIZE = 100;

    /** Guards against an unbounded loop if the API ever stops honoring `offset`. */
    private const int MAX_PAGES = 20;

    private const int TIMEOUT_SECONDS = 30;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    public static function provides(): FeedProvider
    {
        return FeedProvider::Uefa;
    }

    public function fetchMatches(MatchSource $source): array
    {
        $competitionId = trim((string) $source->feedRef);

        if ('' === $competitionId) {
            throw FeedPayloadInvalid::missingFeedRef(self::provides()->label(), 'UEFA competitionId (1 / 14 / 2019)');
        }

        $rows = $this->fetchRows($competitionId, $this->seasonYear($source));

        if ([] === $rows) {
            throw FeedPayloadInvalid::emptyResponse(self::provides()->label(), $competitionId);
        }

        $snapshots = [];
        $unfinishedRows = [];

        foreach ($rows as $index => $row) {
            if ($this->isUndrawnTie($row)) {
                continue;
            }

            try {
                $snapshots[] = $this->snapshotFromRow($index, $row);
            } catch (FeedPayloadInvalid $e) {
                // Around a draw UEFA publishes a tie in instalments: the fixture
                // first, then the team names, then the kickoff time — and each
                // gap has already failed a whole source for hours (2026-08-12:
                // Liga mistrů, team objects carrying neither a name nor the
                // isPlaceHolder flag; 2026-08-13: Evropská liga, rows with no
                // kickOffTime.dateTime). The tie exists but the feed has not
                // finished describing it — the same situation as an undrawn tie,
                // and one unfinished row must not take the other ninety down
                // with it. The next poll picks it up.
                $unfinishedRows[] = $e->getMessage();
                $this->logger->warning('UEFA feed row is not complete yet — skipped until the feed fills it in.', [
                    'matchSourceId' => (string) $source->id,
                    'competitionId' => $competitionId,
                    'row' => $index,
                    'externalId' => $row['id'] ?? null,
                    'problem' => $e->getMessage(),
                ]);
            }
        }

        // A draw window leaves SOME rows unfinished; NOT ONE readable row means
        // the payload shape changed under us, and returning [] would read as
        // „nothing changed" on every poll, forever.
        if ([] !== $unfinishedRows && [] === $snapshots) {
            throw FeedPayloadInvalid::unusableRows(self::provides()->label(), sprintf('not one of %d rows could be read (%s) — has the payload shape changed?', count($unfinishedRows), $unfinishedRows[0]));
        }

        return $snapshots;
    }

    /**
     * UEFA publishes a knockout fixture BEFORE its draw, with participants like
     * „Lyon or Sparta Praha" flagged `isPlaceHolder`. Those are not teams: they
     * resolve against nothing, so without this every poll between a fixture's
     * publication and its draw would report a fistful of unpairable „teams" and
     * bury the ones that genuinely need an alias. They come back as real names
     * once the draw happens, which is when the match is worth importing anyway.
     *
     * @param array<mixed> $row
     */
    private function isUndrawnTie(array $row): bool
    {
        foreach (['homeTeam', 'awayTeam'] as $key) {
            $team = $row[$key] ?? null;

            if (is_array($team) && true === ($team['isPlaceHolder'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * UEFA labels a season by the year it ENDS (2026/27 → `seasonYear=2027`).
     * Derived from the source's own window so a source never silently keeps
     * polling last season after the rollover.
     */
    private function seasonYear(MatchSource $source): int
    {
        $reference = $source->endAt ?? $source->startAt;

        if (null === $reference) {
            return (int) $source->createdAt->format('Y') + 1;
        }

        $month = (int) $reference->format('n');
        $year = (int) $reference->format('Y');

        // A window ending in the second half of a calendar year belongs to the
        // season that will end the FOLLOWING summer.
        return $month >= 7 ? $year + 1 : $year;
    }

    /**
     * @return list<array<mixed>>
     */
    private function fetchRows(string $competitionId, int $seasonYear): array
    {
        $rows = [];

        for ($page = 0; $page < self::MAX_PAGES; ++$page) {
            $batch = $this->requestPage($competitionId, $seasonYear, $page * self::PAGE_SIZE);

            foreach ($batch as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }

            if (count($batch) < self::PAGE_SIZE) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return list<mixed>
     */
    private function requestPage(string $competitionId, int $seasonYear, int $offset): array
    {
        try {
            $payload = $this->httpClient->request('GET', self::BASE_URL, [
                'query' => [
                    'competitionId' => $competitionId,
                    'seasonYear' => $seasonYear,
                    'limit' => self::PAGE_SIZE,
                    'offset' => $offset,
                ],
                'timeout' => self::TIMEOUT_SECONDS,
                'headers' => ['Accept' => 'application/json'],
            ])->toArray();
        } catch (HttpExceptionInterface|\JsonException $e) {
            throw FeedPayloadInvalid::requestFailed(self::provides()->label(), $e->getMessage());
        }

        if (!array_is_list($payload)) {
            throw FeedPayloadInvalid::requestFailed(self::provides()->label(), 'expected a JSON array of matches');
        }

        return $payload;
    }

    /**
     * @param array<mixed> $row
     */
    private function snapshotFromRow(int $index, array $row): MatchSnapshot
    {
        $externalId = $row['id'] ?? null;

        if (!is_string($externalId) && !is_int($externalId)) {
            throw FeedPayloadInvalid::invalidRow($index, 'missing match id');
        }

        $homeTeam = $this->teamName($row, 'homeTeam');
        $awayTeam = $this->teamName($row, 'awayTeam');

        if (null === $homeTeam || null === $awayTeam) {
            throw FeedPayloadInvalid::invalidRow($index, 'missing home/away team');
        }

        $rawStatus = is_string($row['status'] ?? null) ? $row['status'] : '';
        $status = $this->status($rawStatus);
        [$homeScore, $awayScore] = $this->regularScore($row);
        $overtime = null !== $homeScore && null !== $awayScore
            ? $this->overtimeWinner($row, $status, $homeScore, $awayScore)?->scoreAfter($homeScore, $awayScore)
            : null;

        return new MatchSnapshot(
            externalId: (string) $externalId,
            homeTeamName: $homeTeam,
            awayTeamName: $awayTeam,
            kickoffUtc: $this->kickoff($index, $row),
            status: $status,
            homeScore: $homeScore,
            awayScore: $awayScore,
            overtimeHomeScore: $overtime[0] ?? null,
            overtimeAwayScore: $overtime[1] ?? null,
            events: $this->trustedEvents($this->events($row), $homeScore, $awayScore),
            round: $this->round($row),
            venue: $this->venue($row),
            rawStatus: $rawStatus,
        );
    }

    private function status(string $raw): FeedMatchStatus
    {
        return match (strtoupper($raw)) {
            'UPCOMING', 'SCHEDULED' => FeedMatchStatus::Scheduled,
            'LIVE' => FeedMatchStatus::Live,
            'FINISHED' => FeedMatchStatus::Finished,
            'POSTPONED' => FeedMatchStatus::Postponed,
            'CANCELLED', 'CANCELED' => FeedMatchStatus::Cancelled,
            default => FeedMatchStatus::Unknown,
        };
    }

    /**
     * The REGULAR-time score — the primary result every rule but `overtime_exact`
     * scores, and the one the app displays. Extra time and the shootout are read
     * separately, as a WINNER, by overtimeWinner() below. Half a pair is no score
     * (applyFinished rejects it either way), so it reads as unknown.
     *
     * @param array<mixed> $row
     *
     * @return array{int|null, int|null}
     */
    private function regularScore(array $row): array
    {
        return $this->scorePair($row, 'regular') ?? [null, null];
    }

    /**
     * Who won after extra time / penalties, or null for „this payload does not
     * say" — which is what the synchronizer treats as unknown.
     *
     * Field shapes observed on the live endpoint on 2026-09-03, over all 428
     * finished 2026/27 UCL+UEL+UECL rows (the doc records the matches):
     *
     *  - `score.regular` — THIS match's 90 minutes;
     *  - `score.total` — THIS match's score including extra time (Pafos–Dinamo
     *    City 2049288: regular 2:2, total 4:2, so Pafos won in the prolongation);
     *  - `score.penalty` — SINGULAR, and only on the leg where the shootout was
     *    actually taken (SK Rapid–Hearts 2049278: regular 1:1, total 2:2,
     *    penalty 3:4);
     *  - `score.aggregate` — the TIE across both legs. Never this match.
     *
     * Two things in the payload look like an answer and are not, so neither is
     * read here. `winner.aggregate` decides the TIE and is repeated on BOTH legs:
     * Egnatia–Celje (2048724) is a 3:3 FIRST leg already carrying
     * `WIN_ON_PENALTIES / Celje` for a shootout taken five days later in Slovenia
     * — trusting it would invent a winner for a match that simply drew. And
     * `winner.match.reason` only ever says DRAW or WIN_REGULAR about the 90
     * minutes (all 112 regular-time draws say DRAW, including the 11 that were
     * then settled in extra time), so it distinguishes nothing.
     *
     * Hence: the winner is THIS match's after-extra-time score, else THIS match's
     * shootout, else nothing. A league-phase draw (Zrinjski–SK Rapid 2046402) has
     * neither and stays a draw; a second leg settled purely on aggregate
     * (Thun–Lech Poznań 2049242, 2:2 with the tie 2:9) has neither either.
     *
     * @param array<mixed> $row
     */
    private function overtimeWinner(array $row, FeedMatchStatus $status, int $homeScore, int $awayScore): ?OvertimeOutcome
    {
        // Only a finished REGULAR-TIME DRAW can have one: a match won inside 90
        // minutes keeps its winner even when the tie went to a shootout after it
        // (Jablonec–Rangers 2049286 won 1:0 with a 4:3 shootout in the row).
        if (FeedMatchStatus::Finished !== $status || $homeScore !== $awayScore) {
            return null;
        }

        $total = $this->scorePair($row, 'total');

        // Extra time only ever ADDS goals; a `total` below the regular score is
        // not a score of this match and is not guessed at.
        if (null !== $total && $total[0] >= $homeScore && $total[1] >= $awayScore && $total[0] !== $total[1]) {
            return new OvertimeOutcome($total[0] > $total[1] ? MatchSide::Home : MatchSide::Away);
        }

        $penalty = $this->scorePair($row, 'penalty');

        if (null !== $penalty && $penalty[0] !== $penalty[1]) {
            return new OvertimeOutcome($penalty[0] > $penalty[1] ? MatchSide::Home : MatchSide::Away);
        }

        return null;
    }

    /**
     * One `score.<key>` object as a home/away pair, or null when the payload has
     * no complete one there.
     *
     * @param array<mixed> $row
     *
     * @return array{int, int}|null
     */
    private function scorePair(array $row, string $key): ?array
    {
        $score = $row['score'] ?? null;
        $pair = is_array($score) ? ($score[$key] ?? null) : null;

        if (!is_array($pair)) {
            return null;
        }

        $home = $pair['home'] ?? null;
        $away = $pair['away'] ?? null;

        return is_int($home) && is_int($away) ? [$home, $away] : null;
    }

    /**
     * An EMPTY sheet is only believable when the scoreboard agrees. Goals with
     * no scorers means UEFA has not published them yet — claiming „verified: no
     * scorers" there would delete manually entered ones and silently zero every
     * `scorer_hit`. Unknown is the safe answer; a genuine 0:0 keeps its empty
     * sheet.
     *
     * @param list<MatchEventInput>|null $events
     *
     * @return list<MatchEventInput>|null
     */
    private function trustedEvents(?array $events, ?int $homeScore, ?int $awayScore): ?array
    {
        if (null === $events || [] !== $events) {
            return $events;
        }

        return 0 === ($homeScore ?? 0) + ($awayScore ?? 0) ? [] : null;
    }

    /**
     * @param array<mixed> $row
     *
     * @return list<MatchEventInput>|null
     */
    private function events(array $row): ?array
    {
        $playerEvents = $row['playerEvents'] ?? null;

        if (!is_array($playerEvents)) {
            // Not „no goals" but „this payload says nothing about events" — an
            // upcoming match must never wipe a stored sheet.
            return null;
        }

        $scorers = $playerEvents['scorers'] ?? null;

        if (!is_array($scorers)) {
            return null;
        }

        $homeTeamId = $this->teamId($row, 'homeTeam');
        $events = [];

        foreach ($scorers as $scorer) {
            if (!is_array($scorer)) {
                continue;
            }

            $name = $this->playerName($scorer);
            $teamId = is_scalar($scorer['teamId'] ?? null) ? (string) $scorer['teamId'] : null;

            if (null === $name || null === $teamId || null === $homeTeamId) {
                // Without a side we cannot attribute the goal; dropping ONE
                // scorer would silently mis-score `scorer_hit`, so the whole
                // sheet becomes „unknown" and stored events survive.
                return null;
            }

            // An own goal counts for the opponent on the scoreboard but is
            // credited to the scoring player's own team in `scorer_hit` terms —
            // it is not a striker's goal and must not score a scorer tip.
            if ('OWN' === strtoupper((string) ($scorer['goalType'] ?? ''))) {
                continue;
            }

            $events[] = new MatchEventInput(
                type: MatchEventType::Goal,
                side: $teamId === $homeTeamId ? MatchSide::Home : MatchSide::Away,
                minute: $this->minute($scorer),
                playerName: $name,
            );
        }

        return $events;
    }

    /**
     * @param array<mixed> $scorer
     */
    private function playerName(array $scorer): ?string
    {
        $player = $scorer['player'] ?? null;

        if (!is_array($player)) {
            return null;
        }

        foreach (['internationalName', 'translationName', 'shortName', 'clubShirtName'] as $key) {
            $value = $player[$key] ?? null;

            if (is_string($value) && '' !== trim($value)) {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $scorer
     */
    private function minute(array $scorer): ?int
    {
        $time = $scorer['time'] ?? null;

        if (is_array($time) && is_int($time['minute'] ?? null)) {
            return $time['minute'];
        }

        return is_int($scorer['minute'] ?? null) ? $scorer['minute'] : null;
    }

    /**
     * @param array<mixed> $row
     */
    private function teamName(array $row, string $key): ?string
    {
        $team = $row[$key] ?? null;

        if (!is_array($team)) {
            return null;
        }

        foreach (['internationalName', 'translationName', 'displayName', 'shortName'] as $nameKey) {
            $value = $team[$nameKey] ?? null;

            if (is_string($value) && '' !== trim($value)) {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $row
     */
    private function teamId(array $row, string $key): ?string
    {
        $team = $row[$key] ?? null;

        if (!is_array($team)) {
            return null;
        }

        $id = $team['id'] ?? $team['teamId'] ?? null;

        return is_scalar($id) ? (string) $id : null;
    }

    /**
     * @param array<mixed> $row
     */
    private function kickoff(int $index, array $row): \DateTimeImmutable
    {
        $kickOff = $row['kickOffTime'] ?? null;
        $dateTime = is_array($kickOff) ? ($kickOff['dateTime'] ?? null) : null;

        if (!is_string($dateTime)) {
            throw FeedPayloadInvalid::invalidRow($index, 'missing kickOffTime.dateTime');
        }

        try {
            return (new \DateTimeImmutable($dateTime))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw FeedPayloadInvalid::invalidRow($index, sprintf('unparseable kickoff "%s"', $dateTime));
        }
    }

    /**
     * Czech round label in the shape the seeded UEFA soutěže already use
     * („3. předkolo – 2. zápas", „Play-off – 1. zápas") so feed-created matches
     * sit next to hand-seeded ones without a visible style break. Round is
     * display-only, so an unmapped phase falls back to UEFA's English name
     * rather than blocking the import.
     *
     * @param array<mixed> $row
     */
    private function round(array $row): ?string
    {
        $round = $row['round'] ?? null;
        $metaData = is_array($round) ? ($round['metaData'] ?? null) : null;
        $phase = is_array($metaData) && is_string($metaData['type'] ?? null) ? $metaData['type'] : null;

        $label = match ($phase) {
            'FIRST_QUALIFYING' => '1. předkolo',
            'SECOND_QUALIFYING' => '2. předkolo',
            'THIRD_QUALIFYING' => '3. předkolo',
            'PLAY_OFF', 'PLAYOFF' => 'Play-off',
            'GROUP_STAGE', 'LEAGUE_STAGE' => $this->matchdayLabel($row) ?? 'Ligová fáze',
            'KNOCKOUT_PLAY_OFFS', 'KNOCKOUT_PLAYOFF' => 'Play-off vyřazovací fáze',
            'ROUND_OF_16' => 'Osmifinále',
            'QUARTER_FINALS' => 'Čtvrtfinále',
            'SEMI_FINALS' => 'Semifinále',
            'FINAL' => 'Finále',
            default => is_array($metaData) && is_string($metaData['name'] ?? null) ? $metaData['name'] : null,
        };

        if (null === $label) {
            return null;
        }

        $leg = $this->legNumber($row);

        return null !== $leg ? sprintf('%s – %d. zápas', $label, $leg) : $label;
    }

    /**
     * @param array<mixed> $row
     */
    private function matchdayLabel(array $row): ?string
    {
        $matchday = $row['matchday'] ?? null;

        if (!is_array($matchday)) {
            return null;
        }

        // „MD3" carries the number we want; the long name is English.
        if (is_string($matchday['name'] ?? null) && 1 === preg_match('/(\d+)/', $matchday['name'], $matches)) {
            return sprintf('Ligová fáze – %d. kolo', (int) $matches[1]);
        }

        return null;
    }

    /**
     * @param array<mixed> $row
     */
    private function legNumber(array $row): ?int
    {
        $leg = $row['leg'] ?? null;

        if (!is_array($leg)) {
            return null;
        }

        return is_int($leg['number'] ?? null) ? $leg['number'] : null;
    }

    /**
     * The stadium name lives only under `translations.name.<LANG>` — there is no
     * flat `name` key on the UEFA stadium object.
     *
     * @param array<mixed> $row
     */
    private function venue(array $row): ?string
    {
        $stadium = $row['stadium'] ?? null;

        if (!is_array($stadium)) {
            return null;
        }

        $translations = $stadium['translations'] ?? null;
        $names = is_array($translations) ? ($translations['name'] ?? null) : null;

        if (!is_array($names)) {
            return null;
        }

        foreach (['CS', 'EN'] as $language) {
            $value = $names[$language] ?? null;

            if (is_string($value) && '' !== trim($value)) {
                return trim($value);
            }
        }

        return null;
    }
}
