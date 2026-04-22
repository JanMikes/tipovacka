<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Query\ListAdminUsers\ListAdminUsers;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListAdminUsersQueryTest extends IntegrationTestCase
{
    public function testReturnsAllUsersByDefault(): void
    {
        $result = $this->queryBus()->handle(new ListAdminUsers());

        $emails = array_map(static fn ($item) => $item->email, $result);

        self::assertContains(AppFixtures::ADMIN_EMAIL, $emails);
        self::assertContains(AppFixtures::VERIFIED_USER_EMAIL, $emails);
        self::assertContains(AppFixtures::UNVERIFIED_USER_EMAIL, $emails);
        self::assertContains(AppFixtures::DELETED_USER_EMAIL, $emails);
    }

    public function testSearchFiltersByEmail(): void
    {
        $result = $this->queryBus()->handle(new ListAdminUsers(search: 'user@'));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::VERIFIED_USER_EMAIL, $result[0]->email);
    }

    public function testSearchFiltersByNickname(): void
    {
        $result = $this->queryBus()->handle(new ListAdminUsers(search: AppFixtures::UNVERIFIED_USER_NICKNAME));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::UNVERIFIED_USER_NICKNAME, $result[0]->nickname);
    }

    public function testFilterByUnverified(): void
    {
        $result = $this->queryBus()->handle(new ListAdminUsers(verified: false));

        $emails = array_map(static fn ($item) => $item->email, $result);

        self::assertContains(AppFixtures::UNVERIFIED_USER_EMAIL, $emails);
        self::assertNotContains(AppFixtures::VERIFIED_USER_EMAIL, $emails);
    }

    public function testFilterByActive(): void
    {
        $result = $this->queryBus()->handle(new ListAdminUsers(active: true));

        foreach ($result as $item) {
            self::assertTrue($item->isActive);
        }
    }

    public function testDeletedUserIsFlagged(): void
    {
        $result = $this->queryBus()->handle(new ListAdminUsers(search: AppFixtures::DELETED_USER_NICKNAME));

        self::assertCount(1, $result);
        self::assertTrue($result[0]->isDeleted);
    }

    public function testFullNameIsPopulatedForUsersWithProfile(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $verified->updateProfile(firstName: 'Jan', lastName: 'Tipař', phone: null, now: $now);
        $em->flush();

        $all = $this->queryBus()->handle(new ListAdminUsers());

        $byId = [];
        foreach ($all as $item) {
            $byId[$item->id->toRfc4122()] = $item;
        }

        self::assertArrayHasKey(AppFixtures::VERIFIED_USER_ID, $byId);
        self::assertSame(AppFixtures::VERIFIED_USER_NICKNAME, $byId[AppFixtures::VERIFIED_USER_ID]->nickname);
        self::assertSame('Jan Tipař', $byId[AppFixtures::VERIFIED_USER_ID]->fullName);

        self::assertArrayHasKey(AppFixtures::ADMIN_ID, $byId);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $byId[AppFixtures::ADMIN_ID]->nickname);
        self::assertNull($byId[AppFixtures::ADMIN_ID]->fullName, 'Admin has no firstName/lastName set in fixtures.');

        self::assertArrayHasKey(AppFixtures::ANONYMOUS_USER_ID, $byId);
        self::assertNull($byId[AppFixtures::ANONYMOUS_USER_ID]->nickname);
        self::assertSame(
            AppFixtures::ANONYMOUS_USER_FIRST_NAME.' '.AppFixtures::ANONYMOUS_USER_LAST_NAME,
            $byId[AppFixtures::ANONYMOUS_USER_ID]->fullName,
        );
    }
}
