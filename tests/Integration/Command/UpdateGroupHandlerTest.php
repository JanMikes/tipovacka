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
            hideOthersTipsBeforeDeadline: false,
            tipsDeadline: null,
        ));

        $em = $this->entityManager();
        $em->clear();

        $group = $em->find(Group::class, $groupId);
        self::assertInstanceOf(Group::class, $group);
        self::assertSame('Upravená parta', $group->name);
        self::assertSame('Nový popis', $group->description);
    }

    public function testPersistsTipVisibilitySettings(): void
    {
        $groupId = Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID);
        $deadline = new \DateTimeImmutable('2025-06-19 09:00:00');

        $this->commandBus()->dispatch(new UpdateGroupCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: $groupId,
            name: 'Parta',
            description: null,
            hideOthersTipsBeforeDeadline: true,
            tipsDeadline: $deadline,
        ));

        $em = $this->entityManager();
        $em->clear();

        $group = $em->find(Group::class, $groupId);
        self::assertInstanceOf(Group::class, $group);
        self::assertTrue($group->hideOthersTipsBeforeDeadline);
        self::assertEquals($deadline, $group->tipsDeadline);
    }

    public function testClearsPreviouslySetTipsDeadline(): void
    {
        $groupId = Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID);
        $deadline = new \DateTimeImmutable('2025-06-19 09:00:00');

        $this->commandBus()->dispatch(new UpdateGroupCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: $groupId,
            name: 'Parta',
            description: null,
            hideOthersTipsBeforeDeadline: true,
            tipsDeadline: $deadline,
        ));

        $this->commandBus()->dispatch(new UpdateGroupCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: $groupId,
            name: 'Parta',
            description: null,
            hideOthersTipsBeforeDeadline: false,
            tipsDeadline: null,
        ));

        $em = $this->entityManager();
        $em->clear();

        $group = $em->find(Group::class, $groupId);
        self::assertInstanceOf(Group::class, $group);
        self::assertFalse($group->hideOthersTipsBeforeDeadline);
        self::assertNull($group->tipsDeadline);
    }
}
