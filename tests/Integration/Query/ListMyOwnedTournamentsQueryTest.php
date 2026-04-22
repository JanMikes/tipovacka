<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListMyOwnedTournaments\ListMyOwnedTournaments;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListMyOwnedTournamentsQueryTest extends IntegrationTestCase
{
    public function testReturnsPrivateTournamentsOwnedByUser(): void
    {
        $result = $this->queryBus()->handle(new ListMyOwnedTournaments(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        $names = array_map(static fn ($item): string => $item->name, $result);

        self::assertContains(AppFixtures::PRIVATE_TOURNAMENT_NAME, $names);
    }

    public function testReturnsPublicTournamentsOwnedByUser(): void
    {
        // Admin owns the fixture public tournament.
        $result = $this->queryBus()->handle(new ListMyOwnedTournaments(
            ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $names = array_map(static fn ($item): string => $item->name, $result);

        self::assertContains(AppFixtures::PUBLIC_TOURNAMENT_NAME, $names);
    }

    public function testReturnsEmptyForUserWithoutTournaments(): void
    {
        $result = $this->queryBus()->handle(new ListMyOwnedTournaments(
            ownerId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
        ));

        self::assertSame([], $result);
    }

    public function testDoesNotReturnOtherUsersTournaments(): void
    {
        // Admin does NOT own the fixture private tournament (verified user does).
        $result = $this->queryBus()->handle(new ListMyOwnedTournaments(
            ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $names = array_map(static fn ($item): string => $item->name, $result);

        self::assertNotContains(AppFixtures::PRIVATE_TOURNAMENT_NAME, $names);
    }
}
