<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\GetMyGuessesInMatchSource\GetMyGuessesInMatchSource;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetMyGuessesInMatchSourceQueryTest extends IntegrationTestCase
{
    public function testReturnsFixtureGuessForAdminInPublicCompetition(): void
    {
        $rows = $this->queryBus()->handle(new GetMyGuessesInMatchSource(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
        ));

        self::assertCount(1, $rows);
        self::assertSame(3, $rows[0]->myHomeScore);
        self::assertSame(0, $rows[0]->myAwayScore);
        self::assertSame(2, $rows[0]->actualHomeScore);
        self::assertSame(1, $rows[0]->actualAwayScore);
    }

    public function testReturnsEmptyForUserWithoutGuesses(): void
    {
        $rows = $this->queryBus()->handle(new GetMyGuessesInMatchSource(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
        ));

        self::assertCount(0, $rows);
    }
}
