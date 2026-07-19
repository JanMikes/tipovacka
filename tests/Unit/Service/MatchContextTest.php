<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Scoring\MatchContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MatchContextTest extends TestCase
{
    public function testEmptyContextHasNoGoals(): void
    {
        $context = MatchContext::empty();

        self::assertSame([], $context->goalScorerPlayerIds);
        self::assertFalse($context->hasGoalBy(Uuid::v7()));
    }

    public function testHasGoalByMatchesByUuidValue(): void
    {
        $playerId = Uuid::v7();
        $other = Uuid::v7();

        $context = new MatchContext(goalScorerPlayerIds: [$playerId]);

        // Value equality, not identity — a re-hydrated Uuid instance matches.
        self::assertTrue($context->hasGoalBy(Uuid::fromString($playerId->toRfc4122())));
        self::assertFalse($context->hasGoalBy($other));
    }
}
