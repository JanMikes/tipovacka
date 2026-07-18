<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Enum\SportMatchState;
use App\Query\ListMatchSourceSportMatches\ListMatchSourceSportMatches;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListMatchSourceSportMatchesQueryTest extends IntegrationTestCase
{
    public function testListsAllNonDeletedMatchesForMatchSource(): void
    {
        $result = $this->queryBus()->handle(new ListMatchSourceSportMatches(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
        ));

        self::assertCount(3, $result);
    }

    public function testFiltersByState(): void
    {
        $result = $this->queryBus()->handle(new ListMatchSourceSportMatches(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
            state: SportMatchState::Finished,
        ));

        self::assertCount(1, $result);
        self::assertSame(SportMatchState::Finished, $result[0]->state);
    }

    public function testListsMatchesForPrivateMatchSource(): void
    {
        $result = $this->queryBus()->handle(new ListMatchSourceSportMatches(
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
        ));

        self::assertCount(1, $result);
    }
}
