<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListMyGroups\ListMyGroups;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListMyGroupsQueryTest extends IntegrationTestCase
{
    public function testOwnerSeesOwnGroup(): void
    {
        $result = $this->queryBus()->handle(new ListMyGroups(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::VERIFIED_GROUP_ID, $result[0]->groupId->toRfc4122());
        self::assertTrue($result[0]->isOwner);
    }

    public function testNonMemberSeesNothing(): void
    {
        $result = $this->queryBus()->handle(new ListMyGroups(
            userId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
        ));

        self::assertCount(0, $result);
    }
}
