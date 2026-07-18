<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListMyAccessiblePrivateMatchSources\ListMyAccessiblePrivateMatchSources;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListMyAccessiblePrivateMatchSourcesQueryTest extends IntegrationTestCase
{
    public function testReturnsPrivateMatchSourceOwnedByUser(): void
    {
        $result = $this->queryBus()->handle(new ListMyAccessiblePrivateMatchSources(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        $names = array_map(static fn ($item): string => $item->name, $result);

        self::assertContains(AppFixtures::PRIVATE_SOURCE_NAME, $names);
    }

    public function testReturnsPrivateMatchSourceWhereUserIsMember(): void
    {
        // Anonymous user is a member of VERIFIED_COMPETITION in PRIVATE_SOURCE but not the owner.
        $result = $this->queryBus()->handle(new ListMyAccessiblePrivateMatchSources(
            userId: Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID),
        ));

        $names = array_map(static fn ($item): string => $item->name, $result);

        self::assertContains(AppFixtures::PRIVATE_SOURCE_NAME, $names);
    }

    public function testDoesNotReturnPublicMatchSources(): void
    {
        // Admin owns the public match source — it must not leak into the private list.
        $result = $this->queryBus()->handle(new ListMyAccessiblePrivateMatchSources(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $names = array_map(static fn ($item): string => $item->name, $result);

        self::assertNotContains(AppFixtures::PUBLIC_SOURCE_NAME, $names);
    }

    public function testReturnsEmptyForUnrelatedUser(): void
    {
        $result = $this->queryBus()->handle(new ListMyAccessiblePrivateMatchSources(
            userId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
        ));

        self::assertSame([], $result);
    }
}
