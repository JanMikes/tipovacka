<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateGroup\CreateGroupCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Group;
use App\Entity\Membership;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class CreateGroupHandlerTest extends IntegrationTestCase
{
    public function testCreatesGroupWithMembership(): void
    {
        $this->commandBus()->dispatch(new CreateGroupCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            tournamentId: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            name: 'Parta',
            description: 'Popis',
            withPin: false,
        ));

        $em = $this->entityManager();
        $em->clear();

        $group = $em->createQueryBuilder()
            ->select('g')
            ->from(Group::class, 'g')
            ->where('g.name = :name')
            ->setParameter('name', 'Parta')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Group::class, $group);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $group->owner->id->toRfc4122());
        self::assertNull($group->pin);
        self::assertNotNull($group->shareableLinkToken);

        $memberships = $em->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.group = :groupId')
            ->setParameter('groupId', $group->id)
            ->getQuery()
            ->getResult();

        self::assertCount(1, $memberships);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $memberships[0]->user->id->toRfc4122());
    }

    public function testCreatesGroupWithPin(): void
    {
        $this->commandBus()->dispatch(new CreateGroupCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            tournamentId: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            name: 'S PINem',
            description: null,
            withPin: true,
        ));

        $em = $this->entityManager();
        $em->clear();

        $group = $em->createQueryBuilder()
            ->select('g')
            ->from(Group::class, 'g')
            ->where('g.name = :name')
            ->setParameter('name', 'S PINem')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Group::class, $group);
        self::assertNotNull($group->pin);
        self::assertMatchesRegularExpression('/^\d{8}$/', $group->pin);
    }
}
