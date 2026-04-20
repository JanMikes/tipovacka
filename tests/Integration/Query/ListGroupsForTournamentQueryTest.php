<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListGroupsForTournament\ListGroupsForTournament;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListGroupsForTournamentQueryTest extends IntegrationTestCase
{
    public function testListsGroupsForPrivateTournament(): void
    {
        $result = $this->queryBus()->handle(new ListGroupsForTournament(
            tournamentId: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
        ));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::VERIFIED_GROUP_ID, $result[0]->groupId->toRfc4122());
        self::assertSame(AppFixtures::VERIFIED_GROUP_NAME, $result[0]->groupName);
    }

    public function testEmptyForTournamentWithoutGroups(): void
    {
        // Use a non-existing tournament ID
        $result = $this->queryBus()->handle(new ListGroupsForTournament(
            tournamentId: Uuid::fromString('019aaaaa-0000-7000-8000-0000000000ff'),
        ));

        self::assertCount(0, $result);
    }
}
