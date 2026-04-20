<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\Command\SoftDeleteTournament\SoftDeleteTournamentCommand;
use App\DataFixtures\AppFixtures;
use App\Enum\TournamentVisibility;
use App\Query\ListAdminTournaments\ListAdminTournaments;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListAdminTournamentsQueryTest extends IntegrationTestCase
{
    public function testReturnsAllTournamentsIncludingDeleted(): void
    {
        $this->commandBus()->dispatch(new SoftDeleteTournamentCommand(
            tournamentId: Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID),
        ));

        $this->entityManager()->clear();

        $result = $this->queryBus()->handle(new ListAdminTournaments());

        self::assertGreaterThanOrEqual(2, count($result));

        $byId = [];
        foreach ($result as $item) {
            $byId[$item->id->toRfc4122()] = $item;
        }

        self::assertArrayHasKey(AppFixtures::PUBLIC_TOURNAMENT_ID, $byId);
        self::assertArrayHasKey(AppFixtures::PRIVATE_TOURNAMENT_ID, $byId);
        self::assertTrue($byId[AppFixtures::PUBLIC_TOURNAMENT_ID]->isDeleted);
    }

    public function testIncludesVisibilitySportAndGroupCount(): void
    {
        $result = $this->queryBus()->handle(new ListAdminTournaments());

        $byId = [];
        foreach ($result as $item) {
            $byId[$item->id->toRfc4122()] = $item;
        }

        $public = $byId[AppFixtures::PUBLIC_TOURNAMENT_ID];
        self::assertSame(TournamentVisibility::Public, $public->visibility);
        self::assertSame('football', $public->sportCode);
        self::assertSame(AppFixtures::ADMIN_NICKNAME, $public->ownerNickname);
        self::assertSame(1, $public->groupCount);
    }
}
