<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\GetMyGuessesInTournament\GetMyGuessesInTournament;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetMyGuessesInTournamentQueryTest extends IntegrationTestCase
{
    public function testReturnsFixtureGuessForAdminInPublicGroup(): void
    {
        $rows = $this->queryBus()->handle(new GetMyGuessesInTournament(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            tournamentId: Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID),
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
        ));

        self::assertCount(1, $rows);
        self::assertSame(3, $rows[0]->myHomeScore);
        self::assertSame(0, $rows[0]->myAwayScore);
        self::assertSame(2, $rows[0]->actualHomeScore);
        self::assertSame(1, $rows[0]->actualAwayScore);
    }

    public function testReturnsEmptyForUserWithoutGuesses(): void
    {
        $rows = $this->queryBus()->handle(new GetMyGuessesInTournament(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            tournamentId: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
        ));

        self::assertCount(0, $rows);
    }
}
