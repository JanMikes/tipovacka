<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RegenerateShareableLink\RegenerateShareableLinkCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class RegenerateShareableLinkHandlerTest extends IntegrationTestCase
{
    public function testRegeneratesToken(): void
    {
        $groupId = Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID);

        $this->commandBus()->dispatch(new RegenerateShareableLinkCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: $groupId,
        ));

        $em = $this->entityManager();
        $em->clear();

        $group = $em->find(Group::class, $groupId);
        self::assertInstanceOf(Group::class, $group);
        self::assertNotNull($group->shareableLinkToken);
        self::assertNotSame(AppFixtures::VERIFIED_GROUP_LINK_TOKEN, $group->shareableLinkToken);
        self::assertSame(48, strlen($group->shareableLinkToken));
    }
}
