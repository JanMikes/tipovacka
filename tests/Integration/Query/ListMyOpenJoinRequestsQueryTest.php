<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListMyOpenJoinRequests\ListMyOpenJoinRequests;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListMyOpenJoinRequestsQueryTest extends IntegrationTestCase
{
    public function testReturnsMyPendingRequests(): void
    {
        $result = $this->queryBus()->handle(new ListMyOpenJoinRequests(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::PUBLIC_GROUP_NAME, $result[0]->groupName);
        self::assertSame(AppFixtures::PUBLIC_TOURNAMENT_NAME, $result[0]->tournamentName);
    }

    public function testEmptyForUserWithoutRequests(): void
    {
        $result = $this->queryBus()->handle(new ListMyOpenJoinRequests(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        self::assertCount(0, $result);
    }
}
