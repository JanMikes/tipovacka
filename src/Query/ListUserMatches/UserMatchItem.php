<?php

declare(strict_types=1);

namespace App\Query\ListUserMatches;

use App\Value\TeamView;
use App\Value\TipStats;
use Symfony\Component\Uid\Uuid;

final readonly class UserMatchItem
{
    /**
     * @param list<TipStats>          $tipStats                 one entry per competition of the user's that
     *                                                          includes this match — the „Rozložení tipů" bar
     *                                                          or its paywall, batch-resolved by TipStatsProvider
     * @param bool                    $isTippable               the match is still open for tipping in at least
     *                                                          one of the user's competitions (per-competition
     *                                                          effective deadlines via EffectiveTipDeadlineResolver)
     * @param int                     $competitionsCount        the user's competitions that include this match
     * @param int                     $guessedCompetitionsCount competitions (of the above) where the user has a tip
     * @param int                     $openCompetitionsCount    competitions (of the above) where tipping is still
     *                                                          open — same semantics as the dashboard's UpcomingMatchItem
     * @param int                     $pendingCompetitionsCount competitions where the tip is missing AND
     *                                                          tipping is still open — i.e. actionable gaps
     * @param list<Uuid>              $competitionIds           the user's competitions that include this match, in
     *                                                          the order the query returned them; the FIRST one is
     *                                                          where a cross-competition card links, since item 22
     *                                                          made every match link soutěž-scoped. The rest stay
     *                                                          reachable through the per-soutěž tip-split strips,
     *                                                          whose headings link per soutěž
     * @param list<Uuid>              $pendingCompetitionIds    the subset of the above where the tip is missing AND
     *                                                          still open; the first one is where the card's tip CTA
     *                                                          points, so it lands where the tip is actually needed
     * @param int|null                $myHomeScore              the viewer's own tip, but ONLY when exactly one
     *                                                          of their competitions includes this match (two
     *                                                          competitions may hold two different tips);
     *                                                          always answered when the query is scoped to one
     * @param int|null                $myAwayScore              see {@see $myHomeScore}
     * @param \DateTimeImmutable|null $opensAt                  when tipping OPENS, and only while the match is
     *                                                          tippable in none of the viewer's soutěže because
     *                                                          it has not opened in any of them yet — the row
     *                                                          then renders as waiting rather than as locked.
     *                                                          Null whenever at least one soutěž takes a tip now
     * @param string|null             $openingNote              the admin's optional explanation shown while
     *                                                          waiting; belongs to the same soutěž as $opensAt
     */
    public function __construct(
        public Uuid $id,
        public Uuid $matchSourceId,
        public string $matchSourceName,
        public TeamView $homeTeam,
        public TeamView $awayTeam,
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
        public array $competitionIds = [],
        public array $pendingCompetitionIds = [],
        public ?int $myHomeScore = null,
        public ?int $myAwayScore = null,
        public array $tipStats = [],
        public ?\DateTimeImmutable $opensAt = null,
        public ?string $openingNote = null,
    ) {
    }
}
