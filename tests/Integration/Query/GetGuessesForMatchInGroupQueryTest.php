<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\GetGuessesForMatchInGroup\GetGuessesForMatchInGroup;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class GetGuessesForMatchInGroupQueryTest extends IntegrationTestCase
{
    public function testReturnsFixtureGuessForPublicGroupOnFinishedMatch(): void
    {
        $result = $this->queryBus()->handle(new GetGuessesForMatchInGroup(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_FINISHED_ID),
            viewerId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        self::assertCount(1, $result->items);
        self::assertSame('admin', $result->items[0]->nickname);
        self::assertSame(3, $result->items[0]->homeScore);
        self::assertSame(0, $result->items[0]->awayScore);
        self::assertTrue($result->items[0]->isMine);
    }

    public function testReturnsEmptyWhenNoGuesses(): void
    {
        $result = $this->queryBus()->handle(new GetGuessesForMatchInGroup(
            groupId: Uuid::fromString(AppFixtures::VERIFIED_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
            viewerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertCount(0, $result->items);
    }

    public function testIsMineFalseForDifferentViewer(): void
    {
        $result = $this->queryBus()->handle(new GetGuessesForMatchInGroup(
            groupId: Uuid::fromString(AppFixtures::PUBLIC_GROUP_ID),
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_FINISHED_ID),
            viewerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertFalse($result->items[0]->isMine);
    }
}
