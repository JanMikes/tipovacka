<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListAdminGroups\ListAdminGroups;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListAdminGroupsQueryTest extends IntegrationTestCase
{
    public function testReturnsAllGroups(): void
    {
        $result = $this->queryBus()->handle(new ListAdminGroups());

        $ids = array_map(static fn ($item) => $item->id->toRfc4122(), $result);

        self::assertContains(AppFixtures::VERIFIED_GROUP_ID, $ids);
        self::assertContains(AppFixtures::PUBLIC_GROUP_ID, $ids);
    }

    public function testFilterByTournament(): void
    {
        $result = $this->queryBus()->handle(new ListAdminGroups(
            tournamentId: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
        ));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::VERIFIED_GROUP_ID, $result[0]->id->toRfc4122());
    }

    public function testIncludesMemberCount(): void
    {
        $result = $this->queryBus()->handle(new ListAdminGroups());

        $byId = [];
        foreach ($result as $item) {
            $byId[$item->id->toRfc4122()] = $item;
        }

        self::assertSame(1, $byId[AppFixtures::VERIFIED_GROUP_ID]->memberCount);
        self::assertSame(1, $byId[AppFixtures::PUBLIC_GROUP_ID]->memberCount);
    }
}
