<?php

declare(strict_types=1);

namespace App\Query\ListUpcomingMatchesForUser;

use Symfony\Component\Uid\Uuid;

final readonly class UpcomingMatchItem
{
    /**
     * @param int $competitionsCount        the user's competitions that include this match
     * @param int $guessedCompetitionsCount competitions (of the above) where the user has a tip
     * @param int $openCompetitionsCount    competitions (of the above) where tipping is still
     *                                      open — per-competition effective deadlines via
     *                                      EffectiveTipDeadlineResolver
     * @param int $pendingCompetitionsCount competitions where the tip is missing AND tipping
     *                                      is still open (open − guessed-among-open)
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
        public int $competitionsCount,
        public int $guessedCompetitionsCount,
        public int $openCompetitionsCount,
        public int $pendingCompetitionsCount,
    ) {
    }
}
