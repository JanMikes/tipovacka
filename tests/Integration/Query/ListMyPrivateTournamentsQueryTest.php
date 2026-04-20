<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListMyPrivateTournaments\ListMyPrivateTournaments;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListMyPrivateTournamentsQueryTest extends IntegrationTestCase
{
    public function testReturnsPrivateTournamentsOwnedByUser(): void
    {
        $result = $this->queryBus()->handle(new ListMyPrivateTournaments(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::PRIVATE_TOURNAMENT_NAME, $result[0]->name);
    }

    public function testReturnsEmptyForUserWithoutTournaments(): void
    {
        $result = $this->queryBus()->handle(new ListMyPrivateTournaments(
            ownerId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
        ));

        self::assertSame([], $result);
    }

    public function testDoesNotReturnOtherUsersPrivateTournaments(): void
    {
        // Admin does NOT own the fixture private tournament (verified user does).
        $result = $this->queryBus()->handle(new ListMyPrivateTournaments(
            ownerId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        self::assertSame([], $result);
    }
}
