<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RegenerateGroupPin\RegenerateGroupPinCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class RegenerateGroupPinHandlerTest extends IntegrationTestCase
{
    public function testRegeneratesPin(): void
    {
        $groupId = Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID);

        $this->commandBus()->dispatch(new RegenerateGroupPinCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: $groupId,
        ));

        $em = $this->entityManager();
        $em->clear();

        $group = $em->find(Group::class, $groupId);
        self::assertInstanceOf(Group::class, $group);
        self::assertNotNull($group->pin);
        self::assertNotSame(AppFixtures::VERIFIED_GROUP_PIN, $group->pin);
        self::assertMatchesRegularExpression('/^\d{8}$/', $group->pin);
    }
}
