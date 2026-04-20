<?php

declare(strict_types=1);

namespace App\Tests\Integration\Entity;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Event\UserDeleted;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class UserSoftDeleteTest extends IntegrationTestCase
{
    public function testSoftDeleteSetsDeletedAtAndRecordsEvent(): void
    {
        $user = $this->entityManager()->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);

        $now = new \DateTimeImmutable('2025-06-15 14:00:00 UTC');
        $user->softDelete($now);

        $events = $user->popEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(UserDeleted::class, $events[0]);

        /** @var UserDeleted $event */
        $event = $events[0];
        self::assertTrue($user->id->equals($event->userId));
        self::assertSame(AppFixtures::VERIFIED_USER_EMAIL, $event->email);
        self::assertSame(AppFixtures::VERIFIED_USER_NICKNAME, $event->nickname);
        self::assertSame($now, $event->occurredOn);

        self::assertTrue($user->isDeleted());
        self::assertSame($now, $user->deletedAt);
    }

    public function testDeletedFixtureUserHasDeletedAtSet(): void
    {
        $user = $this->entityManager()->find(User::class, Uuid::fromString(AppFixtures::DELETED_USER_ID));

        self::assertNotNull($user);
        self::assertTrue($user->isDeleted());
    }
}
