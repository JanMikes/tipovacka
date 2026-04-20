<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\UpdateTournament\UpdateTournamentCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Tournament;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateTournamentHandlerTest extends IntegrationTestCase
{
    public function testUpdatesTournamentDetails(): void
    {
        $tournamentId = Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID);
        $startAt = new \DateTimeImmutable('2025-08-01 18:00:00 UTC');

        $this->commandBus()->dispatch(new UpdateTournamentCommand(
            tournamentId: $tournamentId,
            name: 'Upravený název',
            description: 'Nový popis',
            startAt: $startAt,
            endAt: null,
        ));

        $em = $this->entityManager();
        $em->clear();

        $tournament = $em->find(Tournament::class, $tournamentId);
        self::assertInstanceOf(Tournament::class, $tournament);
        self::assertSame('Upravený název', $tournament->name);
        self::assertSame('Nový popis', $tournament->description);
        self::assertEquals($startAt, $tournament->startAt);
    }
}
