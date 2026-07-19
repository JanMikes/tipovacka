<?php

declare(strict_types=1);

namespace App\Query\ListUserMatches;

use Symfony\Component\Uid\Uuid;

final readonly class UserMatchItem
{
    /**
     * @param bool $isTippable               the match is still open for tipping in at least
     *                                       one of the user's competitions (per-competition
     *                                       effective deadlines via EffectiveTipDeadlineResolver)
     * @param int  $competitionsCount        the user's competitions that include this match
     * @param int  $guessedCompetitionsCount competitions (of the above) where the user has a tip
     * @param int  $openCompetitionsCount    competitions (of the above) where tipping is still
     *                                       open — same semantics as the dashboard's UpcomingMatchItem
     * @param int  $pendingCompetitionsCount competitions where the tip is missing AND
     *                                       tipping is still open — i.e. actionable gaps
     */
    public function __construct(
        public Uuid $id,
        public Uuid $matchSourceId,
        public string $matchSourceName,
        public string $homeTeam,
        public string $awayTeam,
        public \DateTimeImmutable $kickoffAt,
        public ?string $venue,
        public ?string $round,
        public bool $isPlayoff,
        public bool $isOpenForGuesses,
        public bool $isFinished,
        public bool $isLive,
        public bool $isPostponed,
        public ?int $homeScore,
        public ?int $awayScore,
        public bool $isTippable,
        public int $competitionsCount,
        public int $guessedCompetitionsCount,
        public int $openCompetitionsCount,
        public int $pendingCompetitionsCount,
    ) {
    }
}
