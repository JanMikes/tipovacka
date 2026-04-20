<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\MarkTournamentFinished\MarkTournamentFinishedCommand;
use App\Command\SoftDeleteTournament\SoftDeleteTournamentCommand;
use App\DataFixtures\AppFixtures;
use App\Query\ListActivePublicTournaments\ListActivePublicTournaments;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListActivePublicTournamentsQueryTest extends IntegrationTestCase
{
    public function testReturnsOnlyActivePublicTournaments(): void
    {
        $result = $this->queryBus()->handle(new ListActivePublicTournaments());

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::PUBLIC_TOURNAMENT_NAME, $result[0]->name);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $result[0]->ownerNickname);
    }

    public function testExcludesFinishedTournaments(): void
    {
        $publicId = Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID);
        $this->commandBus()->dispatch(new MarkTournamentFinishedCommand(tournamentId: $publicId));

        $result = $this->queryBus()->handle(new ListActivePublicTournaments());

        self::assertCount(0, $result);
    }

    public function testExcludesDeletedTournaments(): void
    {
        $publicId = Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID);
        $this->commandBus()->dispatch(new SoftDeleteTournamentCommand(tournamentId: $publicId));

        $result = $this->queryBus()->handle(new ListActivePublicTournaments());

        self::assertCount(0, $result);
    }

    public function testExcludesPrivateTournaments(): void
    {
        $result = $this->queryBus()->handle(new ListActivePublicTournaments());

        foreach ($result as $item) {
            self::assertNotSame(AppFixtures::PRIVATE_TOURNAMENT_NAME, $item->name);
        }
    }
}
