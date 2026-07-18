<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListCompetitionsForMatchSource\ListCompetitionsForMatchSource;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListCompetitionsForMatchSourceQueryTest extends IntegrationTestCase
{
    public function testListsCompetitionsForPrivateMatchSource(): void
    {
        $result = $this->queryBus()->handle(new ListCompetitionsForMatchSource(
            matchSourceId: Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID),
        ));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::VERIFIED_COMPETITION_ID, $result[0]->competitionId->toRfc4122());
        self::assertSame(AppFixtures::VERIFIED_COMPETITION_NAME, $result[0]->competitionName);
    }

    public function testEmptyForMatchSourceWithoutCompetitions(): void
    {
        // Use a non-existing match source ID
        $result = $this->queryBus()->handle(new ListCompetitionsForMatchSource(
            matchSourceId: Uuid::fromString('019aaaaa-0000-7000-8000-0000000000ff'),
        ));

        self::assertCount(0, $result);
    }
}
