<?php

declare(strict_types=1);

namespace App\Value;

use App\Query\GetCompetitionLeaderboard\LeaderboardRow;
use Symfony\Component\Uid\Uuid;

/**
 * „Tvoje pozice" — one viewer's place in one board, derived from the board itself.
 *
 * Item 05 needed it for the `.you-strip` on `/zebricek`, item 06 for the hero card on
 * `/nastenka`; both must agree, so the derivation lives here once. Reading it off the
 * already-loaded rows (instead of a second query) is deliberate: under a windowed
 * leaderboard tab the strip can never contradict the re-ranked table.
 */
final readonly class LeaderboardStanding
{
    private function __construct(
        /** The viewer's own row — rank, points, Δ, accuracy, streak, … */
        public LeaderboardRow $row,
        /** Everybody on the board, the „/ 42" denominator. */
        public int $playerCount,
        /** Points still needed to reach rank 3; null once the viewer is there. */
        public ?int $gapToTop3,
        /** Points still needed to reach rank 5; null once the viewer is there. */
        public ?int $gapToTop5,
    ) {
    }

    /**
     * @param list<LeaderboardRow> $rows
     */
    public static function fromRows(array $rows, ?Uuid $userId): ?self
    {
        if (null === $userId) {
            return null;
        }

        $meRow = null;

        foreach ($rows as $row) {
            if ($row->userId->equals($userId)) {
                $meRow = $row;

                break;
            }
        }

        if (null === $meRow) {
            return null;
        }

        return new self(
            row: $meRow,
            playerCount: count($rows),
            gapToTop3: self::gapToRank($rows, $meRow, 3),
            gapToTop5: self::gapToRank($rows, $meRow, 5),
        );
    }

    /**
     * Points the viewer still needs to reach `$rank` („do TOP 5" / „do TOP 3").
     * Null once they are already there, or when the board is shorter than the rank.
     *
     * @param list<LeaderboardRow> $rows
     */
    private static function gapToRank(array $rows, LeaderboardRow $meRow, int $rank): ?int
    {
        if ($meRow->rank <= $rank || count($rows) < $rank) {
            return null;
        }

        return max(0, $rows[$rank - 1]->totalPoints - $meRow->totalPoints);
    }
}
