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
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sportmonks v3 — the paid subscription, covering the leagues neither FAČR nor
 * UEFA files (Premier League, Bundesliga, La Liga, FA Cup, Carabao Cup, MS 2026).
 * `feedRef` is the Sportmonks league id (8 = Premier League, 82 = Bundesliga, …).
 *
 * Fetches a WINDOW around today rather than the whole season:
 *
 *   GET /v3/football/fixtures/between/{from}/{to}
 *         ?filters=fixtureLeagues:<ref>
 *         &include=participants;scores;state;round;venue;events
 *
 * A season has ~380 fixtures but only a handful can change on any given day, so
 * a ±window keeps the payload small while still catching kickoff moves well
 * before anyone tips them. The window is deliberately asymmetric: a few days
 * back (late result corrections) against a few weeks forward (TV reschedules).
 *
 * The window is SELF-HEALING: it always reaches back to the oldest stored match
 * still awaiting a result (`$awaitingResultSince`). Before that, a feed outage
 * longer than the back window stranded every match played during it — exactly
 * what happened 2026-10-28 → 2026-11-17, when the subscription stopped covering
 * the Premier League and 20 played matches were never finished nor evaluated.
 *
 * An EMPTY answer is never taken at face value. Sportmonks reports a league the
 * subscription no longer covers as HTTP 200 with no rows and the same message
 * it uses for a genuinely empty range, so an empty window is cross-checked: it
 * fails when results are known to be missing, or when the league is not among
 * the subscription's leagues (`GET /v3/my/leagues`). Only a covered league with
 * nothing outstanding (a winter break) may legitimately come back empty.
 *
 * Rate limit is PER ENTITY and plan-dependent (2500/h on Starter, ~180/h on
 * the free plan) and every response restates the remaining budget; one poll
 * costs 1 request per bound source (+1 per extra page, +1 per 90 days of a
 * widened window), so even the free plan is far from exhausted.
 *
 * The token travels in the `Authorization` header, never the query string —
 * request URLs end up in INFO logs, and INFO is shipped to Sentry Logs.
 */
final readonly class SportmonksMatchDataProvider implements MatchDataProvider
{
    private const string BASE_URL = 'https://api.sportmonks.com/v3/football/fixtures/between';

    private const string MY_LEAGUES_URL = 'https://api.sportmonks.com/v3/my/leagues';

    private const string INCLUDES = 'participants;scores;state;round;venue;events';

    /**
     * Keep the span under Sportmonks' hard limit: a range over 100 days is
     * rejected outright („You requested a date range of N days. The maximum
     * range is 100 days."), so these two must always sum to less than that.
     */
    private const string WINDOW_BACK = '-7 days';

    private const string WINDOW_FORWARD = '+30 days';

    /**
     * How far the self-healing window may reach back for a match still awaiting
     * its result. Longer than any plausible outage, short enough that one
     * forgotten fixture cannot make every poll fetch the whole season.
     */
    private const string HEAL_LIMIT_BACK = '-120 days';

    /**
     * On the FIRST fetch of a source the window is not enough: a season's other
     * ~350 fixtures have to be visible at once, or `app:matches:adopt-external-ids`
     * can only bridge the handful currently in range and every later fixture
     * enters as a duplicate. So an unpolled source is fetched season-wide, in
     * chunks under the 100-day cap, and every fetch after that is the window.
     */
    private const string SEASON_BACK = '-60 days';

    private const string SEASON_FORWARD = '+330 days';

    private const int CHUNK_DAYS = 90;

    private const int PAGE_SIZE = 50;

    /** Guards against an unbounded loop if `has_more` ever misbehaves. */
    private const int MAX_PAGES = 20;

    private const int TIMEOUT_SECONDS = 30;

    /** Sportmonks type_id of a scored goal (14 = Goal, 16 = Penalty). */
    private const array GOAL_TYPE_IDS = [14, 16];

    /** Own goals count on the scoreboard but never score a `scorer_hit` tip. */
    private const int OWN_GOAL_TYPE_ID = 15;

    public function __construct(
        private HttpClientInterface $httpClient,
        private ClockInterface $clock,
        #[Autowire(env: 'default::SPORTMONKS_API_KEY')]
        private ?string $apiKey,
    ) {
    }

    public static function provides(): FeedProvider
    {
        return FeedProvider::Sportmonks;
    }

    public function fetchMatches(MatchSource $source, ?\DateTimeImmutable $awaitingResultSince = null): array
    {
        $leagueId = trim((string) $source->feedRef);

        if ('' === $leagueId) {
            throw FeedPayloadInvalid::missingFeedRef(self::provides()->label(), 'Sportmonks league id');
        }

        if (null === $this->apiKey || '' === $this->apiKey) {
            throw FeedPayloadInvalid::missingCredentials(self::provides()->label(), 'SPORTMONKS_API_KEY');
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $firstFetch = null === $source->feedPolledAt;

        [$start, $end] = $firstFetch
            ? [$now->modify(self::SEASON_BACK), $now->modify(self::SEASON_FORWARD)]
            : [$this->windowStart($now, $awaitingResultSince), $now->modify(self::WINDOW_FORWARD)];

        $rows = [];
        $plans = [];

        foreach ($this->chunks($start, $end) as [$from, $to]) {
            foreach ($this->fetchRows($leagueId, $from, $to, $plans) as $row) {
                $rows[] = $row;
            }
        }

        if ([] === $rows) {
            $this->assertEmptyIsGenuine($leagueId, $awaitingResultSince, $plans);
        }

        $snapshots = [];

        foreach ($rows as $index => $row) {
            $snapshots[] = $this->snapshotFromRow($index, $row);
        }

        return $snapshots;
    }

    /**
     * The usual few days back, or further when an older stored match is still
     * waiting for its result — a day of slack before its kickoff absorbs any
     * timezone or kickoff-move skew, and the reach is capped.
     */
    private function windowStart(\DateTimeImmutable $now, ?\DateTimeImmutable $awaitingResultSince): \DateTimeImmutable
    {
        $start = $now->modify(self::WINDOW_BACK);

        if (null === $awaitingResultSince) {
            return $start;
        }

        $healFrom = max($awaitingResultSince->modify('-1 day'), $now->modify(self::HEAL_LIMIT_BACK));

        return min($start, $healFrom);
    }

    /**
     * An empty window is believable only for a league the subscription covers
     * and with no result outstanding. Anything else is an outage dressed as a
     * quiet week, and must fail the job instead of reporting „0 unchanged".
     *
     * @param list<string> $plans
     */
    private function assertEmptyIsGenuine(string $leagueId, ?\DateTimeImmutable $awaitingResultSince, array $plans): void
    {
        if (!in_array($leagueId, $this->subscribedLeagueIds(), true)) {
            throw FeedPayloadInvalid::notInSubscription(self::provides()->label(), $leagueId, $plans);
        }

        if (null !== $awaitingResultSince) {
            throw FeedPayloadInvalid::emptyWhileAwaitingResults(self::provides()->label(), $leagueId, $awaitingResultSince);
        }
    }

    /**
     * @return list<string>
     */
    private function subscribedLeagueIds(): array
    {
        $ids = [];

        for ($page = 1; $page <= self::MAX_PAGES; ++$page) {
            $payload = $this->get(self::MY_LEAGUES_URL, ['per_page' => self::PAGE_SIZE, 'page' => $page]);
            $data = $payload['data'] ?? null;

            if (!is_array($data)) {
                break;
            }

            foreach ($data as $league) {
                $id = is_array($league) ? ($league['id'] ?? null) : null;

                if (is_int($id) || is_string($id)) {
                    $ids[] = (string) $id;
                }
            }

            $pagination = $payload['pagination'] ?? null;

            if (!is_array($pagination) || true !== ($pagination['has_more'] ?? false)) {
                break;
            }
        }

        return $ids;
    }

    /**
     * Split a span into request-sized date ranges (Sportmonks rejects anything
     * over 100 days).
     *
     * @return list<array{string, string}>
     */
    private function chunks(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $chunks = [];
        $cursor = $start;

        while ($cursor < $end) {
            $chunkEnd = min($cursor->modify(sprintf('+%d days', self::CHUNK_DAYS)), $end);
            $chunks[] = [$cursor->format('Y-m-d'), $chunkEnd->format('Y-m-d')];
            $cursor = $chunkEnd->modify('+1 day');
        }

        return $chunks;
    }

    /**
     * @param list<string> $plans filled with the subscription's plan names, for the error message
     *
     * @return list<array<mixed>>
     */
    private function fetchRows(string $leagueId, string $from, string $to, array &$plans): array
    {
        $rows = [];

        for ($page = 1; $page <= self::MAX_PAGES; ++$page) {
            $payload = $this->get(sprintf('%s/%s/%s', self::BASE_URL, $from, $to), [
                'filters' => sprintf('fixtureLeagues:%s', $leagueId),
                'include' => self::INCLUDES,
                'per_page' => self::PAGE_SIZE,
                'page' => $page,
            ]);

            $plans = $this->planNames($payload) ?: $plans;
            $data = $payload['data'] ?? null;

            if (!is_array($data)) {
                break;
            }

            foreach ($data as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }

            $pagination = $payload['pagination'] ?? null;

            if (!is_array($pagination) || true !== ($pagination['has_more'] ?? false)) {
                break;
            }
        }

        return $rows;
    }

    /**
     * Every response restates the subscription; its plan names are what tells a
     * human at a glance that „Starter" quietly became „Football Free Plan".
     *
     * @param array<mixed> $payload
     *
     * @return list<string>
     */
    private function planNames(array $payload): array
    {
        $names = [];
        $subscriptions = $payload['subscription'] ?? null;

        foreach (is_array($subscriptions) ? $subscriptions : [] as $subscription) {
            $plans = is_array($subscription) ? ($subscription['plans'] ?? null) : null;

            foreach (is_array($plans) ? $plans : [] as $plan) {
                $name = is_array($plan) ? ($plan['plan'] ?? null) : null;

                if (is_string($name) && '' !== $name) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * @param array<string, int|string> $query
     *
     * @return array<mixed>
     */
    private function get(string $url, array $query): array
    {
        try {
            return $this->httpClient->request('GET', $url, [
                'query' => $query,
                'timeout' => self::TIMEOUT_SECONDS,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => (string) $this->apiKey,
                ],
            ])->toArray();
        } catch (HttpExceptionInterface|\JsonException $e) {
            throw FeedPayloadInvalid::requestFailed(self::provides()->label(), $e->getMessage());
        }
    }

    /**
     * @param array<mixed> $row
     */
    private function snapshotFromRow(int $index, array $row): MatchSnapshot
    {
        $externalId = $row['id'] ?? null;

        if (!is_int($externalId) && !is_string($externalId)) {
            throw FeedPayloadInvalid::invalidRow($index, 'missing fixture id');
        }

        [$homeId, $homeName] = $this->participant($row, 'home');
        [$awayId, $awayName] = $this->participant($row, 'away');

        if (null === $homeName || null === $awayName) {
            throw FeedPayloadInvalid::invalidRow($index, 'missing home/away participant');
        }

        $rawStatus = $this->rawStatus($row);
        [$homeScore, $awayScore] = $this->currentScore($row, $homeId, $awayId);

        return new MatchSnapshot(
            externalId: (string) $externalId,
            homeTeamName: $homeName,
            awayTeamName: $awayName,
            kickoffUtc: $this->kickoff($index, $row),
            status: $this->status($rawStatus),
            homeScore: $homeScore,
            awayScore: $awayScore,
            events: $this->trustedEvents($this->events($row, $homeId), $homeScore, $awayScore),
            round: $this->round($row),
            venue: $this->venue($row),
            rawStatus: $rawStatus,
        );
    }

    /**
     * The complete state table, from GET /v3/football/states (25 rows). Every
     * state is mapped deliberately: the four that need a human — a suspended,
     * abandoned or interrupted match, and one Sportmonks itself flags as
     * awaiting updates — become Unknown so the synchronizer reports them
     * instead of writing a half-played result.
     */
    private function status(string $raw): FeedMatchStatus
    {
        return match ($raw) {
            'NS', 'TBA', 'PENDING', 'DELAYED' => FeedMatchStatus::Scheduled,
            'INPLAY_1ST_HALF', 'INPLAY_2ND_HALF', 'HT', 'BREAK',
            'INPLAY_ET', 'INPLAY_ET_2ND_HALF', 'EXTRA_TIME_BREAK',
            'INPLAY_PENALTIES', 'PEN_BREAK' => FeedMatchStatus::Live,
            // AWARDED / WO are decided results (kontumace); they carry a score.
            'FT', 'AET', 'FT_PEN', 'AWARDED', 'WO' => FeedMatchStatus::Finished,
            'POSTPONED' => FeedMatchStatus::Postponed,
            'CANCELLED', 'DELETED' => FeedMatchStatus::Cancelled,
            default => FeedMatchStatus::Unknown,
        };
    }

    /**
     * @param array<mixed> $row
     */
    private function rawStatus(array $row): string
    {
        $state = $row['state'] ?? null;

        if (is_array($state) && is_string($state['state'] ?? null)) {
            return $state['state'];
        }

        return '';
    }

    /**
     * Participants carry their side in `meta.location`, so home/away never
     * depends on array order.
     *
     * @param array<mixed> $row
     *
     * @return array{int|null, string|null}
     */
    private function participant(array $row, string $location): array
    {
        $participants = $row['participants'] ?? null;

        if (!is_array($participants)) {
            return [null, null];
        }

        foreach ($participants as $participant) {
            if (!is_array($participant)) {
                continue;
            }

            $meta = $participant['meta'] ?? null;

            if (!is_array($meta) || ($meta['location'] ?? null) !== $location) {
                continue;
            }

            $name = $participant['name'] ?? null;
            $id = $participant['id'] ?? null;

            return [
                is_int($id) ? $id : null,
                is_string($name) && '' !== trim($name) ? trim($name) : null,
            ];
        }

        return [null, null];
    }

    /**
     * The CURRENT score rows — one per participant. Sportmonks also emits
     * 1ST_HALF / 2ND_HALF rows, but football has no per-period tips enabled in
     * this app by default and a half-time pair is not a `PeriodScores` the
     * entity would accept unless it is complete, so only the running/final
     * total is taken.
     *
     * @param array<mixed> $row
     *
     * @return array{int|null, int|null}
     */
    private function currentScore(array $row, ?int $homeId, ?int $awayId): array
    {
        $scores = $row['scores'] ?? null;

        if (!is_array($scores) || null === $homeId || null === $awayId) {
            return [null, null];
        }

        $home = null;
        $away = null;

        foreach ($scores as $entry) {
            if (!is_array($entry) || 'CURRENT' !== ($entry['description'] ?? null)) {
                continue;
            }

            $score = $entry['score'] ?? null;
            $goals = is_array($score) ? ($score['goals'] ?? null) : null;
            $participantId = $entry['participant_id'] ?? null;

            if (!is_int($goals)) {
                continue;
            }

            if ($participantId === $homeId) {
                $home = $goals;
            } elseif ($participantId === $awayId) {
                $away = $goals;
            }
        }

        // A half-known score is worse than none — it would be written as 0.
        return null !== $home && null !== $away ? [$home, $away] : [null, null];
    }

    /**
     * An EMPTY sheet is only believable when the scoreboard agrees. Goals with
     * no goal events means the include did not populate (or every goal was an
     * own goal) — claiming „verified: no scorers" there would delete manually
     * entered ones and silently zero every `scorer_hit`. Unknown is the safe
     * answer; a genuine 0:0 still reports its empty sheet.
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
    private function events(array $row, ?int $homeId): ?array
    {
        $events = $row['events'] ?? null;

        if (!is_array($events) || null === $homeId) {
            return null;
        }

        $goals = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $typeId = $event['type_id'] ?? null;

            if (self::OWN_GOAL_TYPE_ID === $typeId) {
                continue;
            }

            if (!is_int($typeId) || !in_array($typeId, self::GOAL_TYPE_IDS, true)) {
                continue;
            }

            $playerName = $event['player_name'] ?? null;
            $participantId = $event['participant_id'] ?? null;

            if (!is_string($playerName) || '' === trim($playerName) || !is_int($participantId)) {
                // Attributing this goal is impossible; leaving the rest in place
                // would silently produce a WRONG complete sheet, so we report
                // „unknown" and stored events survive untouched.
                return null;
            }

            $goals[] = new MatchEventInput(
                type: MatchEventType::Goal,
                side: $participantId === $homeId ? MatchSide::Home : MatchSide::Away,
                minute: is_int($event['minute'] ?? null) ? $event['minute'] : null,
                playerName: trim($playerName),
            );
        }

        return $goals;
    }

    /**
     * @param array<mixed> $row
     */
    private function kickoff(int $index, array $row): \DateTimeImmutable
    {
        $timestamp = $row['starting_at_timestamp'] ?? null;

        if (is_int($timestamp)) {
            return (new \DateTimeImmutable('@'.$timestamp))->setTimezone(new \DateTimeZone('UTC'));
        }

        $startingAt = $row['starting_at'] ?? null;

        if (!is_string($startingAt)) {
            throw FeedPayloadInvalid::invalidRow($index, 'missing starting_at');
        }

        try {
            // Sportmonks renders `starting_at` in the account timezone, which the
            // API defaults to UTC (echoed as `timezone` in every response).
            return new \DateTimeImmutable($startingAt, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw FeedPayloadInvalid::invalidRow($index, sprintf('unparseable starting_at "%s"', $startingAt));
        }
    }

    /**
     * @param array<mixed> $row
     */
    private function round(array $row): ?string
    {
        $round = $row['round'] ?? null;

        if (!is_array($round)) {
            return null;
        }

        $name = $round['name'] ?? null;

        if (is_int($name) || (is_string($name) && 1 === preg_match('/^\d+$/', $name))) {
            return sprintf('%d. kolo', (int) $name);
        }

        return is_string($name) && '' !== trim($name) ? trim($name) : null;
    }

    /**
     * @param array<mixed> $row
     */
    private function venue(array $row): ?string
    {
        $venue = $row['venue'] ?? null;

        if (!is_array($venue)) {
            return null;
        }

        $name = $venue['name'] ?? null;

        return is_string($name) && '' !== trim($name) ? trim($name) : null;
    }
}
