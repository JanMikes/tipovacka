<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreatePublicTournament\CreatePublicTournamentCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Tournament;
use App\Enum\TournamentVisibility;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class CreatePublicTournamentHandlerTest extends IntegrationTestCase
{
    public function testCreatesPublicTournament(): void
    {
        $this->commandBus()->dispatch(new CreatePublicTournamentCommand(
            adminId: Uuid::fromString(AppFixtures::ADMIN_ID),
            name: 'Nový veřejný turnaj',
            description: 'Popis',
            startAt: null,
            endAt: null,
        ));

        $em = $this->entityManager();
        $em->clear();

        $tournament = $em->createQueryBuilder()
            ->select('t')
            ->from(Tournament::class, 't')
            ->where('t.name = :name')
            ->setParameter('name', 'Nový veřejný turnaj')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Tournament::class, $tournament);
        self::assertSame(TournamentVisibility::Public, $tournament->visibility);
        self::assertTrue($tournament->isPublic);
        self::assertFalse($tournament->isFinished);
        self::assertFalse($tournament->isDeleted());
        self::assertSame(AppFixtures::ADMIN_ID, $tournament->owner->id->toRfc4122());
        self::assertSame('football', $tournament->sport->code);
    }
}
