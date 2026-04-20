<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\MarkTournamentFinished\MarkTournamentFinishedCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Tournament;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class MarkTournamentFinishedHandlerTest extends IntegrationTestCase
{
    public function testMarksTournamentAsFinished(): void
    {
        $tournamentId = Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID);

        $this->commandBus()->dispatch(new MarkTournamentFinishedCommand(tournamentId: $tournamentId));

        $em = $this->entityManager();
        $em->clear();

        $tournament = $em->find(Tournament::class, $tournamentId);
        self::assertInstanceOf(Tournament::class, $tournament);
        self::assertTrue($tournament->isFinished);
        self::assertNotNull($tournament->finishedAt);
    }

    public function testThrowsWhenAlreadyFinished(): void
    {
        $tournamentId = Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID);

        $this->commandBus()->dispatch(new MarkTournamentFinishedCommand(tournamentId: $tournamentId));

        $this->expectException(HandlerFailedException::class);
        $this->commandBus()->dispatch(new MarkTournamentFinishedCommand(tournamentId: $tournamentId));
    }
}
