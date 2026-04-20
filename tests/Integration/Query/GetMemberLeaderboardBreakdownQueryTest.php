<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\GetMemberLeaderboardBreakdown\GetMemberLeaderboardBreakdown;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetMemberLeaderboardBreakdownQueryTest extends IntegrationTestCase
{
    public function testAdminBreakdownReturnsFinishedMatchesWithEvaluation(): void
    {
        $result = $this->queryBus()->handle(new GetMemberLeaderboardBreakdown(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        self::assertCount(1, $result->rows);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $result->nickname);
        self::assertSame(3, $result->totalPoints);

        $row = $result->rows[0];
        self::assertSame(2, $row->actualHomeScore);
        self::assertSame(1, $row->actualAwayScore);
        self::assertSame(3, $row->myHomeScore);
        self::assertSame(0, $row->myAwayScore);
        self::assertSame(3, $row->totalPoints);
        self::assertCount(1, $row->breakdown);
        self::assertSame('correct_outcome', $row->breakdown[0]->ruleIdentifier);
        self::assertSame(3, $row->breakdown[0]->points);
    }

    public function testUserWithoutGuessStillSeesFinishedMatchesAsEmpty(): void
    {
        $result = $this->queryBus()->handle(new GetMemberLeaderboardBreakdown(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertCount(1, $result->rows);
        self::assertSame(0, $result->totalPoints);

        $row = $result->rows[0];
        self::assertNull($row->myHomeScore);
        self::assertNull($row->myAwayScore);
        self::assertSame(0, $row->totalPoints);
        self::assertSame([], $row->breakdown);
    }
}
