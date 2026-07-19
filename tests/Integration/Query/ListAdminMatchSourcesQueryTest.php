<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\SoftDeleteMatchSource\SoftDeleteMatchSourceCommand;
use App\DataFixtures\AppFixtures;
use App\Enum\MatchSourceKind;
use App\Query\ListAdminMatchSources\ListAdminMatchSources;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListAdminMatchSourcesQueryTest extends IntegrationTestCase
{
    public function testReturnsAllMatchSourcesIncludingDeleted(): void
    {
        $this->commandBus()->dispatch(new SoftDeleteMatchSourceCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
        ));

        $this->entityManager()->clear();

        $result = $this->queryBus()->handle(new ListAdminMatchSources());

        self::assertGreaterThanOrEqual(2, count($result));

        $byId = [];
        foreach ($result as $item) {
            $byId[$item->id->toRfc4122()] = $item;
        }

        self::assertArrayHasKey(AppFixtures::PUBLIC_SOURCE_ID, $byId);
        self::assertArrayHasKey(AppFixtures::PRIVATE_SOURCE_ID, $byId);
        self::assertTrue($byId[AppFixtures::PUBLIC_SOURCE_ID]->isDeleted);
    }

    public function testIncludesVisibilitySportAndCompetitionCount(): void
    {
        $result = $this->queryBus()->handle(new ListAdminMatchSources());

        $byId = [];
        foreach ($result as $item) {
            $byId[$item->id->toRfc4122()] = $item;
        }

        $public = $byId[AppFixtures::PUBLIC_SOURCE_ID];
        self::assertSame(MatchSourceKind::Curated, $public->kind);
        self::assertSame('football', $public->sportCode);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $public->ownerNickname);
        // PUBLIC_COMPETITION + SUBSET_COMPETITION + the two S09 global competitions
        // + the S10 PREMIUM_COMPETITION + the S10 BOOSTS_COMPETITION all live on the
        // public source.
        self::assertSame(6, $public->competitionCount);
    }
}
