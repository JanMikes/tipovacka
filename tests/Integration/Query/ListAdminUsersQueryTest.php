<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListAdminUsers\ListAdminUsers;
use App\Tests\Support\IntegrationTestCase;

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
}
