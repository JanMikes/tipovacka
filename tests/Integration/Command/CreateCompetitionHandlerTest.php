<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\CompetitionMatchSelection;
use App\Entity\Membership;
use App\Enum\CompetitionMatchSelectionMode;
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

    public function testCreatesCompetitionWithTipSettingsAndSubsetSelection(): void
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            name: 'Jen vybrané',
            description: null,
            withPin: false,
            hideOthersTipsBeforeDeadline: true,
            selectionMode: CompetitionMatchSelectionMode::Subset,
            includePlayoff: true,
            selectedMatchIds: [
                Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
                Uuid::fromString(AppFixtures::MATCH_PLAYOFF_ID),
            ],
        ));

        $em = $this->entityManager();
        $em->clear();

        $competition = $em->createQueryBuilder()
            ->select('g')
            ->from(Competition::class, 'g')
            ->where('g.name = :name')
            ->setParameter('name', 'Jen vybrané')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Competition::class, $competition);
        self::assertTrue($competition->hideOthersTipsBeforeDeadline);
        // A fresh competition is never pre-locked; locking is a separate command.
        self::assertNull($competition->tipsLockedAt);
        self::assertSame(CompetitionMatchSelectionMode::Subset, $competition->selectionMode);

        $selections = $em->createQueryBuilder()
            ->select('s')
            ->from(CompetitionMatchSelection::class, 's')
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', $competition->id)
            ->getQuery()
            ->getResult();

        self::assertCount(2, $selections);
    }

    public function testSubsetCreateIgnoresMatchesFromOtherSources(): void
    {
        $this->commandBus()->dispatch(new CreateCompetitionCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            name: 'Cizí zápas',
            description: null,
            withPin: false,
            selectionMode: CompetitionMatchSelectionMode::Subset,
            selectedMatchIds: [
                Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
                // Belongs to the PRIVATE source — must be silently skipped.
                Uuid::fromString(AppFixtures::MATCH_PRIVATE_SCHEDULED_ID),
            ],
        ));

        $em = $this->entityManager();
        $em->clear();

        $competition = $em->createQueryBuilder()
            ->select('g')
            ->from(Competition::class, 'g')
            ->where('g.name = :name')
            ->setParameter('name', 'Cizí zápas')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Competition::class, $competition);

        $selections = $em->createQueryBuilder()
            ->select('s')
            ->from(CompetitionMatchSelection::class, 's')
            ->where('s.competition = :competitionId')
            ->setParameter('competitionId', $competition->id)
            ->getQuery()
            ->getResult();

        self::assertCount(1, $selections);
        self::assertSame(AppFixtures::MATCH_SCHEDULED_ID, $selections[0]->sportMatch->id->toRfc4122());
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
