<?php

declare(strict_types=1);

namespace App\Service\Feed;

use App\Enum\FeedMatchStatus;
use App\Value\MatchEventInput;

/**
 * One match as an external feed sees it right now — the provider-neutral input
 * of FeedSynchronizer. Team names are the provider's raw spellings; resolution
 * to directory teams (incl. aliases) happens in the synchronizer.
 */
final readonly class MatchSnapshot
{
    /**
     * @param list<array{int, int}>|null $periodScores complete per-period [home, away]
     *                                                 pairs, or null when the provider doesn't supply them
     * @param list<MatchEventInput>|null $events       the COMPLETE event sheet, or null when
     *                                                 the provider knows nothing about events (score-only feeds) — null never
     *                                                 overwrites stored events, an empty list means „verified: no events"
     */
    public function __construct(
        public string $externalId,
        public string $homeTeamName,
        public string $awayTeamName,
        public \DateTimeImmutable $kickoffUtc,
        public FeedMatchStatus $status,
        public ?int $homeScore = null,
        public ?int $awayScore = null,
        public ?array $periodScores = null,
        public ?int $overtimeHomeScore = null,
        public ?int $overtimeAwayScore = null,
        public ?array $events = null,
        public ?string $round = null,
        public ?string $venue = null,
        /** The provider's unmapped status code, kept for Unknown-status diagnostics. */
        public ?string $rawStatus = null,
    ) {
    }

    /** Human-readable identification for sync reports (a hook is impossible in a readonly class). */
    public function label(): string
    {
        return sprintf('%s – %s (%s)', $this->homeTeamName, $this->awayTeamName, $this->externalId);
    }
}
