<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListUpcomingMatchesForUser\ListUpcomingMatchesForUser;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListUpcomingMatchesForUserQueryTest extends IntegrationTestCase
{
    public function testReturnsUpcomingScheduledMatchesForMember(): void
    {
        // admin is a member of PUBLIC_GROUP (public tournament)
        $result = $this->queryBus()->handle(new ListUpcomingMatchesForUser(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        // MockClock is at 2025-06-15 12:00 UTC, scheduled fixture is 2025-06-20 18:00 UTC
        self::assertCount(1, $result);
        self::assertSame('Sparta Praha', $result[0]->homeTeam);
        self::assertSame(AppFixtures::PUBLIC_TOURNAMENT_NAME, $result[0]->tournamentName);
    }

    public function testReturnsEmptyForUserWithNoMemberships(): void
    {
        // unverified user is not a member of any group
        $result = $this->queryBus()->handle(new ListUpcomingMatchesForUser(
            userId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
        ));

        self::assertCount(0, $result);
    }
}
