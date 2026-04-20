<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListPendingInvitationsForGroup\ListPendingInvitationsForGroup;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListPendingInvitationsForGroupQueryTest extends IntegrationTestCase
{
    public function testReturnsPendingFixtureInvitation(): void
    {
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $result = $this->queryBus()->handle(new ListPendingInvitationsForGroup(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
            now: $now,
        ));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::PENDING_INVITATION_EMAIL, $result[0]->email);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $result[0]->inviterNickname);
    }

    public function testEmptyForGroupWithoutInvitations(): void
    {
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $result = $this->queryBus()->handle(new ListPendingInvitationsForGroup(
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            now: $now,
        ));

        self::assertCount(0, $result);
    }
}
