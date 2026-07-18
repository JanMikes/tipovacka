<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListAdminCompetitions\ListAdminCompetitions;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListAdminCompetitionsQueryTest extends IntegrationTestCase
{
    public function testReturnsAllCompetitions(): void
    {
        $result = $this->queryBus()->handle(new ListAdminCompetitions());

        $ids = array_map(static fn ($item) => $item->id->toRfc4122(), $result);

        self::assertContains(AppFixtures::VERIFIED_COMPETITION_ID, $ids);
        self::assertContains(AppFixtures::PUBLIC_COMPETITION_ID, $ids);
    }

    public function testFilterByMatchSource(): void
    {
        $result = $this->queryBus()->handle(new ListAdminCompetitions(
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
        ));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::VERIFIED_COMPETITION_ID, $result[0]->id->toRfc4122());
    }

    public function testIncludesMemberCount(): void
    {
        $result = $this->queryBus()->handle(new ListAdminCompetitions());

        $byId = [];
        foreach ($result as $item) {
            $byId[$item->id->toRfc4122()] = $item;
        }

        // Owner + anonymous fixture member.
        self::assertSame(2, $byId[AppFixtures::VERIFIED_COMPETITION_ID]->memberCount);
        self::assertSame(1, $byId[AppFixtures::PUBLIC_COMPETITION_ID]->memberCount);
    }
}
