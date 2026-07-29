<?php

declare(strict_types=1);

namespace App\Query\ListMyPlayingCompetitions;

use Symfony\Component\Uid\Uuid;

final readonly class PlayingCompetitionItem
{
    public function __construct(
        public Uuid $competitionId,
        public string $name,
        public string $matchSourceName,
        public bool $viewerIsOwner,
        public bool $isFinished,
        public int $rank,
        public int $memberCount,
        public int $totalPoints,
        /** Points the viewer collected in {@see $currentRound}; 0 when that round is not scored yet. */
        public int $roundPoints,
        /** Round label of the competition's current kolo/fáze, null when its matches carry none. */
        public ?string $currentRound,
        public int $liveMatchCount,
        /** Matches still open for the viewer to tip and not yet tipped. */
        public int $pendingTipCount,
        /** The earliest deadline among those — the „Tipuj do …" moment. */
        public ?\DateTimeImmutable $nextDeadlineAt,
        /** Kickoff of the next match ahead, whether or not it is still tippable. */
        public ?\DateTimeImmutable $nextKickoffAt,
    ) {
    }
}
