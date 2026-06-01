<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\GetMemberGroupStats\GetMemberGroupStats;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetMemberGroupStatsQueryTest extends IntegrationTestCase
{
    public function testStatsForMemberWithOneEvaluatedGuess(): void
    {
        // Fixture: admin owns PUBLIC_GROUP (sole member) and has one evaluated guess
        // on the finished match — tipped 3:0 vs actual 2:1 → correct_outcome (+3).
        // That is a scored, non-exact (partial) hit.
        $result = $this->queryBus()->handle(new GetMemberGroupStats(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
        ));

        self::assertTrue($result->isMember);
        self::assertSame(1, $result->rank);
        self::assertSame(1, $result->totalMembers);
        self::assertSame(3, $result->totalPoints);
        self::assertSame(1, $result->evaluatedCount);
        self::assertSame(1, $result->scoredCount);
        self::assertSame(0, $result->exactCount);
        self::assertSame(1, $result->partialCount);
        self::assertSame(100, $result->accuracyPercent);
        self::assertSame(1, $result->streak);
    }

    public function testStatsForNonMemberReportsNotMember(): void
    {
        // The verified user is not a member of PUBLIC_GROUP in the baseline fixtures.
        $result = $this->queryBus()->handle(new GetMemberGroupStats(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
        ));

        self::assertFalse($result->isMember);
        self::assertSame(0, $result->rank);
        self::assertSame(0, $result->totalPoints);
        self::assertSame(0, $result->evaluatedCount);
        // Total members still reflects the group (admin only).
        self::assertSame(1, $result->totalMembers);
    }
}
