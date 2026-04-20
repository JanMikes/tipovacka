<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RevokeShareableLink\RevokeShareableLinkCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class RevokeShareableLinkHandlerTest extends IntegrationTestCase
{
    public function testRevokesToken(): void
    {
        $groupId = Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID);

        $this->commandBus()->dispatch(new RevokeShareableLinkCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: $groupId,
        ));

        $em = $this->entityManager();
        $em->clear();

        $group = $em->find(Group::class, $groupId);
        self::assertInstanceOf(Group::class, $group);
        self::assertNull($group->shareableLinkToken);
    }
}
