<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\GetMatchPickDistribution\GetMatchPickDistribution;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetMatchPickDistributionQueryTest extends IntegrationTestCase
{
    public function testDistributionBucketsTheSingleHomeWinGuess(): void
    {
        // Fixture: the public competition has one guess on the finished match — admin 3:0 (home win).
        $result = $this->queryBus()->handle(new GetMatchPickDistribution(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_FINISHED_ID),
        ));

        self::assertSame(1, $result->total);
        self::assertSame(1, $result->homeWinCount);
        self::assertSame(0, $result->drawCount);
        self::assertSame(0, $result->awayWinCount);
        self::assertSame(100, $result->homeWinPercent);
        self::assertSame(0, $result->drawPercent);
        self::assertSame(0, $result->awayWinPercent);
    }

    public function testEmptyDistributionWhenNoGuesses(): void
    {
        $result = $this->queryBus()->handle(new GetMatchPickDistribution(
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
        ));

        self::assertSame(0, $result->total);
        self::assertSame(0, $result->homeWinPercent);
        self::assertSame(0, $result->drawPercent);
        self::assertSame(0, $result->awayWinPercent);
    }
}
