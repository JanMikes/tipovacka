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
        // admin is a member of PUBLIC_COMPETITION (public match source)
        $result = $this->queryBus()->handle(new ListUpcomingMatchesForUser(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        // MockClock is at 2025-06-15 12:00 UTC; upcoming scheduled fixtures are
        // MATCH_SCHEDULED (2025-06-20) and MATCH_PLAYOFF (2025-06-22) — the admin's
        // PUBLIC_COMPETITION is mode All with includePlayoff, so both belong.
        self::assertCount(2, $result);
        self::assertSame('Sparta Praha', $result[0]->homeTeam);
        self::assertSame('Real Madrid', $result[1]->homeTeam);
        self::assertSame(AppFixtures::PUBLIC_SOURCE_NAME, $result[0]->matchSourceName);
    }

    public function testReturnsEmptyForUserWithNoMemberships(): void
    {
        // unverified user is not a member of any competition
        $result = $this->queryBus()->handle(new ListUpcomingMatchesForUser(
            userId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
        ));

        self::assertCount(0, $result);
    }
}
