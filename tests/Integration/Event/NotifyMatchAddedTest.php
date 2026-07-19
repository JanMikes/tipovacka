<?php

declare(strict_types=1);

namespace App\Tests\Integration\Event;

use App\Command\CreateSportMatch\CreateSportMatchCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Notification;
use App\Enum\NotificationType;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class NotifyMatchAddedTest extends IntegrationTestCase
{
    // A unique team name so counts isolate THIS match from fixture notifications.
    private const string NEEDLE = 'ZeeltTým';

    public function testMatchAddedNotifiesStartedCompetitionsButNotExcludedOnes(): void
    {
        // PUBLIC_SOURCE already has a past kickoff (2025-06-10), so its mode-All
        // competitions have started — a new match is a late addition worth announcing.
        $this->commandBus()->dispatch(new CreateSportMatchCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeTeam: self::NEEDLE,
            awayTeam: 'Nový Hosté',
            kickoffAt: new \DateTimeImmutable('2025-06-25 18:00:00 UTC'),
            venue: null,
        ));

        // A started mode-All competition on the source → members announced.
        self::assertGreaterThan(0, $this->countForNewMatch(AppFixtures::PUBLIC_COMPETITION_ID));

        // Subset competition on the same source does NOT auto-include the new match.
        self::assertSame(0, $this->countForNewMatch(AppFixtures::SUBSET_COMPETITION_ID));

        // A competition on a DIFFERENT source is untouched.
        self::assertSame(0, $this->countForNewMatch(AppFixtures::VERIFIED_COMPETITION_ID));
    }

    public function testNoMatchAddedForNotYetStartedCompetition(): void
    {
        // PRIVATE_SOURCE's only match is in the future (2025-06-20) so
        // VERIFIED_COMPETITION has NOT started — adding another future match to it
        // is just part of the initial schedule, not an announcement.
        $this->commandBus()->dispatch(new CreateSportMatchCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            homeTeam: self::NEEDLE,
            awayTeam: 'Budoucí B',
            kickoffAt: new \DateTimeImmutable('2025-06-21 18:00:00 UTC'),
            venue: null,
        ));

        self::assertSame(0, $this->countForNewMatch(AppFixtures::VERIFIED_COMPETITION_ID));
    }

    /**
     * Counts match_added notifications about the just-created match (matched by the
     * unique team name), scoped to a competition — isolating them from any fixture
     * notifications that predate the test.
     */
    private function countForNewMatch(string $competitionId): int
    {
        return (int) $this->entityManager()->createQueryBuilder()
            ->select('COUNT(n.id)')
            ->from(Notification::class, 'n')
            ->where('n.competition = :competitionId')
            ->andWhere('n.type = :type')
            ->andWhere('n.body LIKE :needle')
            ->setParameter('competitionId', Uuid::fromString($competitionId))
            ->setParameter('type', NotificationType::MatchAdded)
            ->setParameter('needle', '%'.self::NEEDLE.'%')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
