<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\GuessScorer;
use App\Enum\MatchSide;
use App\Tests\Unit\Rule\RuleTestFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class GuessScorerEntityTest extends TestCase
{
    public function testConstructionHoldsGuessPlayerSideAndTimestamp(): void
    {
        $now = RuleTestFactory::now();
        $guess = RuleTestFactory::guess(2, 1);
        $player = RuleTestFactory::player('Jan Novák');

        $scorer = new GuessScorer(
            id: Uuid::v7(),
            guess: $guess,
            player: $player,
            side: MatchSide::Away,
            createdAt: $now,
        );

        self::assertSame($guess, $scorer->guess);
        self::assertSame($player, $scorer->player);
        self::assertSame(MatchSide::Away, $scorer->side);
        self::assertSame($now, $scorer->createdAt);
    }

    public function testGuessScorerCollectionRoundTrip(): void
    {
        $now = RuleTestFactory::now();
        $guess = RuleTestFactory::guess(2, 1);
        $player = RuleTestFactory::player('Jan Novák');

        $scorer = new GuessScorer(
            id: Uuid::v7(),
            guess: $guess,
            player: $player,
            side: MatchSide::Home,
            createdAt: $now,
        );

        $guess->addScorer($scorer);
        self::assertCount(1, $guess->scorers);

        $later = $now->modify('+1 hour');
        $guess->removeScorer($scorer, $later);

        self::assertCount(0, $guess->scorers);
        self::assertSame($later, $guess->updatedAt);
    }
}
