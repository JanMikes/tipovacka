<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
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

    public function testMissingTipPillCountsOnlyCompetitionsStillOpen(): void
    {
        // VERIFIED_USER's upcoming match (Tygři vs Lvi) is missing a tip in
        // their only competition ⇒ pending 1. After the competition locks its
        // tips, the gap is no longer actionable ⇒ pending 0 (pill hidden).
        $userId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);

        $result = $this->queryBus()->handle(new ListUpcomingMatchesForUser(userId: $userId));
        self::assertCount(1, $result);
        self::assertSame('Tygři', $result[0]->homeTeam);
        self::assertSame(1, $result[0]->competitionsCount);
        self::assertSame(1, $result[0]->pendingCompetitionsCount);

        $em = $this->entityManager();
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertNotNull($competition);
        $competition->lockTips(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $competition->popEvents();
        $em->flush();

        $result = $this->queryBus()->handle(new ListUpcomingMatchesForUser(userId: $userId));
        self::assertCount(1, $result);
        self::assertSame(1, $result[0]->competitionsCount);
        self::assertSame(0, $result[0]->pendingCompetitionsCount);
    }
}
