<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
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

    public function testMemberFullNameSubtitleBranches(): void
    {
        $em = $this->entityManager();
        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');

        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $verified->updateProfile(firstName: 'Jan', lastName: 'Tipař', phone: null, now: $now);
        $em->flush();

        $result = $this->queryBus()->handle(new GetGroupDetail(
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            viewerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            viewerIsAdmin: false,
        ));

        $byUser = [];
        foreach ($result->members as $m) {
            $byUser[$m->userId->toRfc4122()] = $m;
        }

        self::assertArrayHasKey(AppFixtures::VERIFIED_USER_ID, $byUser);
        self::assertSame(AppFixtures::VERIFIED_USER_NICKNAME, $byUser[AppFixtures::VERIFIED_USER_ID]->displayName);
        self::assertSame('Jan Tipař', $byUser[AppFixtures::VERIFIED_USER_ID]->fullName);

        self::assertArrayHasKey(AppFixtures::ANONYMOUS_USER_ID, $byUser);
        self::assertSame(
            AppFixtures::ANONYMOUS_USER_FIRST_NAME.' '.AppFixtures::ANONYMOUS_USER_LAST_NAME,
            $byUser[AppFixtures::ANONYMOUS_USER_ID]->displayName,
        );
        self::assertNull($byUser[AppFixtures::ANONYMOUS_USER_ID]->fullName);
    }
}
