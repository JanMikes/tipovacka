<?php

declare(strict_types=1);

namespace App\Tests\Unit\Value;

use App\Enum\MatchSide;
use App\Value\OvertimeOutcome;
use PHPUnit\Framework\TestCase;

final class OvertimeOutcomeTest extends TestCase
{
    public function testScoreAfterIsTheDrawPlusOneGoalForTheWinner(): void
    {
        self::assertSame([3, 2], (new OvertimeOutcome(MatchSide::Home))->scoreAfter(2, 2));
        self::assertSame([0, 1], (new OvertimeOutcome(MatchSide::Away))->scoreAfter(0, 0));
    }

    public function testWinnerIsReadBackFromTheStoredPair(): void
    {
        self::assertSame(MatchSide::Home, OvertimeOutcome::fromScores(2, 2, 3, 2)?->winner);
        self::assertSame(MatchSide::Away, OvertimeOutcome::fromScores(1, 1, 1, 2)?->winner);
    }

    /**
     * @return iterable<string, array{int, int, int, int}>
     */
    public static function notAnOutcome(): iterable
    {
        yield 'regular time was not a draw' => [2, 1, 3, 1];
        yield 'still a draw after overtime' => [2, 2, 3, 3];
        yield 'two goals ahead is a score, not a winner' => [2, 2, 4, 2];
        yield 'winner but the other side moved too' => [1, 1, 2, 2];
        yield 'below the regular draw' => [2, 2, 3, 1];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('notAnOutcome')]
    public function testAnythingElseIsNotAnOutcome(int $home, int $away, int $overtimeHome, int $overtimeAway): void
    {
        self::assertNull(OvertimeOutcome::fromScores($home, $away, $overtimeHome, $overtimeAway));
    }
}
