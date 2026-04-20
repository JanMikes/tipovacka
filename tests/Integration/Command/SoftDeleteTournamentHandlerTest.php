<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SoftDeleteTournament\SoftDeleteTournamentCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Tournament;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class SoftDeleteTournamentHandlerTest extends IntegrationTestCase
{
    public function testSoftDeletesTournament(): void
    {
        $tournamentId = Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID);

        $this->commandBus()->dispatch(new SoftDeleteTournamentCommand(tournamentId: $tournamentId));

        $em = $this->entityManager();
        $em->clear();

        $tournament = $em->find(Tournament::class, $tournamentId);
        self::assertInstanceOf(Tournament::class, $tournament);
        self::assertTrue($tournament->isDeleted());
        self::assertNotNull($tournament->deletedAt);
    }

    public function testIsIdempotent(): void
    {
        $tournamentId = Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID);

        $this->commandBus()->dispatch(new SoftDeleteTournamentCommand(tournamentId: $tournamentId));
        // Second dispatch should not throw
        $this->commandBus()->dispatch(new SoftDeleteTournamentCommand(tournamentId: $tournamentId));

        $em = $this->entityManager();
        $em->clear();

        $tournament = $em->find(Tournament::class, $tournamentId);
        self::assertInstanceOf(Tournament::class, $tournament);
        self::assertTrue($tournament->isDeleted());
    }
}
