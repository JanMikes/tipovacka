<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Enum\SportMatchState;
use App\Query\ListTournamentSportMatches\ListTournamentSportMatches;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListTournamentSportMatchesQueryTest extends IntegrationTestCase
{
    public function testListsAllNonDeletedMatchesForTournament(): void
    {
        $result = $this->queryBus()->handle(new ListTournamentSportMatches(
            tournamentId: Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID),
        ));

        self::assertCount(3, $result);
    }

    public function testFiltersByState(): void
    {
        $result = $this->queryBus()->handle(new ListTournamentSportMatches(
            tournamentId: Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID),
            state: SportMatchState::Finished,
        ));

        self::assertCount(1, $result);
        self::assertSame(SportMatchState::Finished, $result[0]->state);
    }

    public function testReturnsEmptyForOtherTournament(): void
    {
        $result = $this->queryBus()->handle(new ListTournamentSportMatches(
            tournamentId: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
        ));

        self::assertCount(0, $result);
    }
}
