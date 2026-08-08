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
 * A FULL provider: finished matches carry `score.regular` / `score.total` and a
 * `playerEvents.scorers[]` array, and the `id` is byte-identical to the
 * externalId already stored for these competitions.
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

        $snapshots = [];

        foreach ($this->fetchRows($competitionId, $this->seasonYear($source)) as $index => $row) {
            $snapshots[] = $this->snapshotFromRow($index, $row);
        }

        if ([] === $snapshots) {
            throw FeedPayloadInvalid::emptyResponse(self::provides()->label(), $competitionId);
        }

        return $snapshots;
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

        return new MatchSnapshot(
            externalId: (string) $externalId,
            homeTeamName: $homeTeam,
            awayTeamName: $awayTeam,
            kickoffUtc: $this->kickoff($index, $row),
            status: $status,
            homeScore: $homeScore,
            awayScore: $awayScore,
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
     * scores. Extra time and shootouts live in `score.total` / `score.penalties`;
     * mapping those onto the single combined overtime pair is a separate,
     * deliberate step (see .docs/MATCH_DATA_FEEDS.md) and is NOT done here, so a
     * knockout tie reports the 90-minute score and nothing invented.
     *
     * @param array<mixed> $row
     *
     * @return array{int|null, int|null}
     */
    private function regularScore(array $row): array
    {
        $score = $row['score'] ?? null;

        if (!is_array($score)) {
            return [null, null];
        }

        $regular = $score['regular'] ?? null;

        if (!is_array($regular)) {
            return [null, null];
        }

        $home = $regular['home'] ?? null;
        $away = $regular['away'] ?? null;

        return [is_int($home) ? $home : null, is_int($away) ? $away : null];
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
