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
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads snapshots from a local JSON file (`feedRef` = path, relative paths
 * resolve against the project dir). The reference MatchDataProvider: it drives
 * the integration tests of the whole sync pipeline and enables dev/staging dry
 * runs with zero network and zero vendor accounts.
 *
 * Expected shape — a JSON array of objects:
 * { "externalId": "8327", "homeTeam": "…", "awayTeam": "…",
 *   "kickoffUtc": "2026-08-01T17:00:00Z", "status": "scheduled|live|finished|postponed|cancelled",
 *   "homeScore"?: 2, "awayScore"?: 1, "periodScores"?: [[1,0],[1,1]],
 *   "overtimeHomeScore"?: 2, "overtimeAwayScore"?: 1,
 *   "events"?: [{ "type": "goal|yellow_card|red_card", "side": "home|away", "minute"?: 51, "player": "…" }],
 *   "round"?: "1. kolo", "venue"?: "…" }
 * An unrecognized status string maps to FeedMatchStatus::Unknown (kept as rawStatus).
 */
final readonly class FixtureFileProvider implements MatchDataProvider
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public static function provides(): FeedProvider
    {
        return FeedProvider::Fixture;
    }

    public function fetchMatches(MatchSource $source): array
    {
        $path = (string) $source->feedRef;
        if (!str_starts_with($path, '/')) {
            $path = $this->projectDir.'/'.$path;
        }

        if (!is_file($path) || !is_readable($path)) {
            throw FeedPayloadInvalid::unreadableFile($path);
        }

        $raw = (string) file_get_contents($path);

        try {
            $rows = json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw FeedPayloadInvalid::notJson($path, $e->getMessage());
        }

        if (!is_array($rows) || !array_is_list($rows)) {
            throw FeedPayloadInvalid::notJson($path, 'expected a top-level array of match objects');
        }

        $snapshots = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw FeedPayloadInvalid::invalidRow($index, 'not an object');
            }

            $snapshots[] = $this->snapshotFromRow($index, $row);
        }

        return $snapshots;
    }

    /**
     * @param array<mixed> $row
     */
    private function snapshotFromRow(int $index, array $row): MatchSnapshot
    {
        $externalId = $row['externalId'] ?? null;
        $homeTeam = $row['homeTeam'] ?? null;
        $awayTeam = $row['awayTeam'] ?? null;
        $kickoff = $row['kickoffUtc'] ?? null;
        $statusRaw = $row['status'] ?? null;

        if (!is_string($externalId) || '' === trim($externalId)) {
            throw FeedPayloadInvalid::invalidRow($index, 'missing externalId');
        }

        if (!is_string($homeTeam) || '' === trim($homeTeam) || !is_string($awayTeam) || '' === trim($awayTeam)) {
            throw FeedPayloadInvalid::invalidRow($index, 'missing homeTeam/awayTeam');
        }

        if (!is_string($kickoff) || !is_string($statusRaw)) {
            throw FeedPayloadInvalid::invalidRow($index, 'missing kickoffUtc/status');
        }

        try {
            $kickoffUtc = (new \DateTimeImmutable($kickoff))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Exception) {
            throw FeedPayloadInvalid::invalidRow($index, sprintf('unparseable kickoffUtc "%s"', $kickoff));
        }

        $status = FeedMatchStatus::tryFrom($statusRaw) ?? FeedMatchStatus::Unknown;

        return new MatchSnapshot(
            externalId: trim($externalId),
            homeTeamName: trim($homeTeam),
            awayTeamName: trim($awayTeam),
            kickoffUtc: $kickoffUtc,
            status: $status,
            homeScore: $this->intOrNull($index, $row, 'homeScore'),
            awayScore: $this->intOrNull($index, $row, 'awayScore'),
            periodScores: $this->periodScores($index, $row),
            overtimeHomeScore: $this->intOrNull($index, $row, 'overtimeHomeScore'),
            overtimeAwayScore: $this->intOrNull($index, $row, 'overtimeAwayScore'),
            events: $this->events($index, $row),
            round: is_string($row['round'] ?? null) ? $row['round'] : null,
            venue: is_string($row['venue'] ?? null) ? $row['venue'] : null,
            rawStatus: $statusRaw,
        );
    }

    /**
     * @param array<mixed> $row
     */
    private function intOrNull(int $index, array $row, string $key): ?int
    {
        $value = $row[$key] ?? null;
        if (null === $value) {
            return null;
        }

        if (!is_int($value)) {
            throw FeedPayloadInvalid::invalidRow($index, sprintf('"%s" must be an integer', $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $row
     *
     * @return list<array{int, int}>|null
     */
    private function periodScores(int $index, array $row): ?array
    {
        $value = $row['periodScores'] ?? null;
        if (null === $value) {
            return null;
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw FeedPayloadInvalid::invalidRow($index, 'periodScores must be an array of [home, away] pairs');
        }

        $pairs = [];
        foreach ($value as $pair) {
            if (!is_array($pair) || 2 !== count($pair) || !is_int($pair[0] ?? null) || !is_int($pair[1] ?? null)) {
                throw FeedPayloadInvalid::invalidRow($index, 'periodScores must be an array of [home, away] pairs');
            }

            $pairs[] = [$pair[0], $pair[1]];
        }

        return $pairs;
    }

    /**
     * @param array<mixed> $row
     *
     * @return list<MatchEventInput>|null
     */
    private function events(int $index, array $row): ?array
    {
        $value = $row['events'] ?? null;
        if (null === $value) {
            return null;
        }

        if (!is_array($value) || !array_is_list($value)) {
            throw FeedPayloadInvalid::invalidRow($index, 'events must be an array of event objects');
        }

        $events = [];
        foreach ($value as $event) {
            if (!is_array($event)) {
                throw FeedPayloadInvalid::invalidRow($index, 'events must be an array of event objects');
            }

            $type = is_string($event['type'] ?? null) ? MatchEventType::tryFrom($event['type']) : null;
            $side = is_string($event['side'] ?? null) ? MatchSide::tryFrom($event['side']) : null;
            $player = $event['player'] ?? null;
            $minute = $event['minute'] ?? null;

            if (null === $type || null === $side || !is_string($player) || '' === trim($player)) {
                throw FeedPayloadInvalid::invalidRow($index, 'event needs a valid type, side and player');
            }

            if (null !== $minute && !is_int($minute)) {
                throw FeedPayloadInvalid::invalidRow($index, 'event minute must be an integer or absent');
            }

            $events[] = new MatchEventInput(
                type: $type,
                side: $side,
                minute: $minute,
                playerName: trim($player),
            );
        }

        return $events;
    }
}
