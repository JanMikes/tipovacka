<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Enum\TournamentVisibility;
use App\Query\GetTournamentDetail\GetTournamentDetail;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class GetTournamentDetailQueryTest extends IntegrationTestCase
{
    public function testReturnsDetailForExistingTournament(): void
    {
        $result = $this->queryBus()->handle(new GetTournamentDetail(
            tournamentId: Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID),
        ));

        self::assertSame(AppFixtures::PRIVATE_TOURNAMENT_NAME, $result->name);
        self::assertSame(TournamentVisibility::Private, $result->visibility);
        self::assertSame(AppFixtures::VERIFIED_USER_NICKNAME, $result->ownerNickname);
        self::assertSame('football', $result->sportCode);
        self::assertSame('Fotbal', $result->sportName);
    }

    public function testThrowsWhenTournamentNotFound(): void
    {
        $this->expectException(HandlerFailedException::class);

        $this->queryBus()->handle(new GetTournamentDetail(
            tournamentId: Uuid::fromString('019bbbbb-0000-7000-8000-000000000099'),
        ));
    }
}
