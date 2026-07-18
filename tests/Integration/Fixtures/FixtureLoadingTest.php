<?php

declare(strict_types=1);

namespace App\Tests\Integration\Fixtures;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionMatchSelection;
use App\Entity\MatchSource;
use App\Entity\Sport;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Enum\MatchSourceKind;
use App\Repository\SportRepository;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class FixtureLoadingTest extends IntegrationTestCase
{
    public function testAdminUserLoaded(): void
    {
        $user = $this->entityManager()->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));

        self::assertNotNull($user);
        self::assertSame(AppFixtures::ADMIN_EMAIL, $user->email);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $user->nickname);
        self::assertTrue($user->isVerified);
        self::assertTrue($user->isActive);
        self::assertFalse($user->isDeleted());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testVerifiedUserLoaded(): void
    {
        $user = $this->entityManager()->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));

        self::assertNotNull($user);
        self::assertTrue($user->isVerified);
        self::assertFalse($user->isDeleted());
    }

    public function testUnverifiedUserLoaded(): void
    {
        $user = $this->entityManager()->find(User::class, Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID));

        self::assertNotNull($user);
        self::assertFalse($user->isVerified);
    }

    public function testDeletedUserLoaded(): void
    {
        $user = $this->entityManager()->find(User::class, Uuid::fromString(AppFixtures::DELETED_USER_ID));

        self::assertNotNull($user);
        self::assertTrue($user->isDeleted());
        self::assertNotNull($user->deletedAt);
    }

    public function testMatchSourceKindsLoaded(): void
    {
        $curated = $this->entityManager()->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        $private = $this->entityManager()->find(MatchSource::class, Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID));

        self::assertNotNull($curated);
        self::assertSame(MatchSourceKind::Curated, $curated->kind);
        self::assertTrue($curated->isCurated);

        self::assertNotNull($private);
        self::assertSame(MatchSourceKind::Private, $private->kind);
        self::assertFalse($private->isCurated);
    }

    public function testSubsetCompetitionLoadedWithSelections(): void
    {
        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString(AppFixtures::SUBSET_COMPETITION_ID));

        self::assertNotNull($competition);
        self::assertSame(CompetitionMatchSelectionMode::Subset, $competition->selectionMode);
        self::assertTrue($competition->includePlayoff);

        $selections = $this->entityManager()->createQueryBuilder()
            ->select('s')
            ->from(CompetitionMatchSelection::class, 's')
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', $competition->id)
            ->getQuery()
            ->getResult();

        self::assertCount(2, $selections);
    }

    public function testPlayoffMatchLoaded(): void
    {
        $match = $this->entityManager()->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_PLAYOFF_ID));

        self::assertNotNull($match);
        self::assertTrue($match->isPlayoff);
        self::assertTrue($match->isScheduled);
    }

    public function testFootballSportSeededByMigration(): void
    {
        /** @var SportRepository $repo */
        $repo = self::getContainer()->get(SportRepository::class);
        $sport = $repo->findByCode('football');

        self::assertNotNull($sport);
        self::assertSame('football', $sport->code);
        self::assertSame('Fotbal', $sport->name);
        self::assertSame(Sport::FOOTBALL_ID, $sport->id->toRfc4122());
    }
}
