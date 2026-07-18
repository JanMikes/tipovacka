<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\GetMatchRanking\GetMatchRanking;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetMatchRankingQueryTest extends IntegrationTestCase
{
    public function testRankingForFinishedMatch(): void
    {
        // Fixture: admin tipped 3:0 on the finished match in PUBLIC_COMPETITION → +3 points.
        $result = $this->queryBus()->handle(new GetMatchRanking(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_FINISHED_ID),
        ));

        self::assertCount(1, $result->rows);

        $row = $result->rows[0];
        self::assertSame(1, $row->rank);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $row->nickname);
        self::assertSame(3, $row->guessHome);
        self::assertSame(0, $row->guessAway);
        self::assertSame(3, $row->totalPoints);
        self::assertSame(AppFixtures::ADMIN_ID, $row->userId->toRfc4122());
    }

    public function testEmptyRankingWhenNoEvaluations(): void
    {
        // The scheduled match has no evaluated guesses.
        $result = $this->queryBus()->handle(new GetMatchRanking(
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
        ));

        self::assertSame([], $result->rows);
    }
}
