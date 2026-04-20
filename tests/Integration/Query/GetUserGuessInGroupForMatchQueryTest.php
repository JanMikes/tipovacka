<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\GetUserGuessInGroupForMatch\GetUserGuessInGroupForMatch;
use App\Query\GetUserGuessInGroupForMatch\UserGuessResult;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetUserGuessInGroupForMatchQueryTest extends IntegrationTestCase
{
    public function testReturnsFixtureGuess(): void
    {
        $result = $this->queryBus()->handle(new GetUserGuessInGroupForMatch(
            userId: Uuid::fromString(AppFixtures::ADMIN_ID),
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_FINISHED_ID),
        ));

        self::assertInstanceOf(UserGuessResult::class, $result);
        self::assertSame(3, $result->homeScore);
        self::assertSame(0, $result->awayScore);
    }

    public function testReturnsNullWhenNoGuess(): void
    {
        $result = $this->queryBus()->handle(new GetUserGuessInGroupForMatch(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
        ));

        self::assertNull($result);
    }
}
