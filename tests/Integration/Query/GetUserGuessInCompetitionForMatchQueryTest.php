<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\GetUserGuessInCompetitionForMatch\GetUserGuessInCompetitionForMatch;
use App\Query\GetUserGuessInCompetitionForMatch\UserGuessResult;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetUserGuessInCompetitionForMatchQueryTest extends IntegrationTestCase
{
    public function testReturnsFixtureGuess(): void
    {
        $result = $this->queryBus()->handle(new GetUserGuessInCompetitionForMatch(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_FINISHED_ID),
        ));

        self::assertInstanceOf(UserGuessResult::class, $result);
        self::assertSame(3, $result->homeScore);
        self::assertSame(0, $result->awayScore);
    }

    public function testReturnsNullWhenNoGuess(): void
    {
        $result = $this->queryBus()->handle(new GetUserGuessInCompetitionForMatch(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
        ));

        self::assertNull($result);
    }
}
