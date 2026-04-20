<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\CreatePrivateTournament\CreatePrivateTournamentCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Tournament;
use App\Enum\TournamentVisibility;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class CreatePrivateTournamentHandlerTest extends IntegrationTestCase
{
    public function testCreatesPrivateTournament(): void
    {
        $this->commandBus()->dispatch(new CreatePrivateTournamentCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            name: 'Soukromý pohár',
            description: null,
            startAt: null,
            endAt: null,
        ));

        $em = $this->entityManager();
        $em->clear();

        $tournament = $em->createQueryBuilder()
            ->select('t')
            ->from(Tournament::class, 't')
            ->where('t.name = :name')
            ->setParameter('name', 'Soukromý pohár')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Tournament::class, $tournament);
        self::assertSame(TournamentVisibility::Private, $tournament->visibility);
        self::assertFalse($tournament->isPublic);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $tournament->owner->id->toRfc4122());
    }
}
