<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListMyOwnedMatchSources\ListMyOwnedMatchSources;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListMyOwnedMatchSourcesQueryTest extends IntegrationTestCase
{
    public function testReturnsPrivateMatchSourcesOwnedByUser(): void
    {
        $result = $this->queryBus()->handle(new ListMyOwnedMatchSources(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        $names = array_map(static fn ($item): string => $item->name, $result);

        self::assertContains(AppFixtures::PRIVATE_SOURCE_NAME, $names);
    }

    public function testReturnsPublicMatchSourcesOwnedByUser(): void
    {
        // Admin owns the fixture public match source.
        $result = $this->queryBus()->handle(new ListMyOwnedMatchSources(
            ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $names = array_map(static fn ($item): string => $item->name, $result);

        self::assertContains(AppFixtures::PUBLIC_SOURCE_NAME, $names);
    }

    public function testReturnsEmptyForUserWithoutMatchSources(): void
    {
        $result = $this->queryBus()->handle(new ListMyOwnedMatchSources(
            ownerId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
        ));

        self::assertSame([], $result);
    }

    public function testDoesNotReturnOtherUsersMatchSources(): void
    {
        // Admin does NOT own the fixture private match source (verified user does).
        $result = $this->queryBus()->handle(new ListMyOwnedMatchSources(
            ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $names = array_map(static fn ($item): string => $item->name, $result);

        self::assertNotContains(AppFixtures::PRIVATE_SOURCE_NAME, $names);
    }
}
