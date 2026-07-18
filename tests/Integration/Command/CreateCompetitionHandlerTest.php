<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\Membership;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class CreateCompetitionHandlerTest extends IntegrationTestCase
{
    public function testCreatesCompetitionWithMembership(): void
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            name: 'Parta',
            description: 'Popis',
            withPin: false,
        ));

        $em = $this->entityManager();
        $em->clear();

        $competition = $em->createQueryBuilder()
            ->select('g')
            ->from(Competition::class, 'g')
            ->where('g.name = :name')
            ->setParameter('name', 'Parta')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Competition::class, $competition);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $competition->owner->id->toRfc4122());
        self::assertNull($competition->pin);
        self::assertNotNull($competition->shareableLinkToken);

        $memberships = $em->createQueryBuilder()
            ->select('m')
            ->from(Membership::class, 'm')
            ->where('m.competition = :competitionId')
            ->setParameter('competitionId', $competition->id)
            ->getQuery()
            ->getResult();

        self::assertCount(1, $memberships);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $memberships[0]->user->id->toRfc4122());
    }

    public function testCreatesCompetitionWithPin(): void
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            name: 'S PINem',
            description: null,
            withPin: true,
        ));

        $em = $this->entityManager();
        $em->clear();

        $competition = $em->createQueryBuilder()
            ->select('g')
            ->from(Competition::class, 'g')
            ->where('g.name = :name')
            ->setParameter('name', 'S PINem')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Competition::class, $competition);
        self::assertNotNull($competition->pin);
        self::assertMatchesRegularExpression('/^\d{8}$/', $competition->pin);
    }
}
