<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListMyAccessiblePrivateTournaments\ListMyAccessiblePrivateTournaments;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListMyAccessiblePrivateTournamentsQueryTest extends IntegrationTestCase
{
    public function testReturnsPrivateTournamentOwnedByUser(): void
    {
        $result = $this->queryBus()->handle(new ListMyAccessiblePrivateTournaments(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        $names = array_map(static fn ($item): string => $item->name, $result);

        self::assertContains(AppFixtures::PRIVATE_TOURNAMENT_NAME, $names);
    }

    public function testReturnsPrivateTournamentWhereUserIsMember(): void
    {
        // Anonymous user is a member of VERIFIED_GROUP in PRIVATE_TOURNAMENT but not the owner.
        $result = $this->queryBus()->handle(new ListMyAccessiblePrivateTournaments(
            userId: Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID),
        ));

        $names = array_map(static fn ($item): string => $item->name, $result);

        self::assertContains(AppFixtures::PRIVATE_TOURNAMENT_NAME, $names);
    }

    public function testDoesNotReturnPublicTournaments(): void
    {
        // Admin owns the public tournament — it must not leak into the private list.
        $result = $this->queryBus()->handle(new ListMyAccessiblePrivateTournaments(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $names = array_map(static fn ($item): string => $item->name, $result);

        self::assertNotContains(AppFixtures::PUBLIC_TOURNAMENT_NAME, $names);
    }

    public function testReturnsEmptyForUnrelatedUser(): void
    {
        $result = $this->queryBus()->handle(new ListMyAccessiblePrivateTournaments(
            userId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
        ));

        self::assertSame([], $result);
    }
}
