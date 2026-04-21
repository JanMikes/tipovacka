<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\GetGroupDetail\GetGroupDetail;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetGroupDetailQueryTest extends IntegrationTestCase
{
    public function testOwnerSeesSecrets(): void
    {
        $result = $this->queryBus()->handle(new GetGroupDetail(
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            viewerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            viewerIsAdmin: false,
        ));

        self::assertSame(AppFixtures::VERIFIED_GROUP_PIN, $result->pin);
        self::assertSame(AppFixtures::VERIFIED_GROUP_LINK_TOKEN, $result->shareableLinkToken);
        // Owner + anonymous fixture member.
        self::assertCount(2, $result->members);
    }

    public function testAdminSeesSecrets(): void
    {
        $result = $this->queryBus()->handle(new GetGroupDetail(
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            viewerId: Uuid::fromString(AppFixtures::ADMIN_ID),
            viewerIsAdmin: true,
        ));

        self::assertSame(AppFixtures::VERIFIED_GROUP_PIN, $result->pin);
    }

    public function testNonOwnerHasSecretsHidden(): void
    {
        $result = $this->queryBus()->handle(new GetGroupDetail(
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            viewerId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
            viewerIsAdmin: false,
        ));

        self::assertNull($result->pin);
        self::assertNull($result->shareableLinkToken);
    }
}
