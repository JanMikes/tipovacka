<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListPendingJoinRequestsForGroup\ListPendingJoinRequestsForGroup;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListPendingJoinRequestsForGroupQueryTest extends IntegrationTestCase
{
    public function testReturnsPendingFixtureRequest(): void
    {
        $result = $this->queryBus()->handle(new ListPendingJoinRequestsForGroup(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
        ));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::VERIFIED_USER_NICKNAME, $result[0]->nickname);
    }
}
