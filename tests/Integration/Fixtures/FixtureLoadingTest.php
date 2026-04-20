<?php

declare(strict_types=1);

namespace App\Tests\Integration\Fixtures;

use App\DataFixtures\AppFixtures;
use App\Entity\Sport;
use App\Entity\User;
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
