<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\UpdateGroup\UpdateGroupCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateGroupHandlerTest extends IntegrationTestCase
{
    public function testUpdatesGroupDetails(): void
    {
        $groupId = Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID);

        $this->commandBus()->dispatch(new UpdateGroupCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: $groupId,
            name: 'Upravená parta',
            description: 'Nový popis',
        ));

        $em = $this->entityManager();
        $em->clear();

        $group = $em->find(Group::class, $groupId);
        self::assertInstanceOf(Group::class, $group);
        self::assertSame('Upravená parta', $group->name);
        self::assertSame('Nový popis', $group->description);
    }
}
