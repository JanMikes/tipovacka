<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RevokeGroupPin\RevokeGroupPinCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class RevokeGroupPinHandlerTest extends IntegrationTestCase
{
    public function testRevokesPin(): void
    {
        $groupId = Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID);

        $this->commandBus()->dispatch(new RevokeGroupPinCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: $groupId,
        ));

        $em = $this->entityManager();
        $em->clear();

        $group = $em->find(Group::class, $groupId);
        self::assertInstanceOf(Group::class, $group);
        self::assertNull($group->pin);
    }
}
