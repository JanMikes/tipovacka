<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListMyCompetitions\ListMyCompetitions;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListMyCompetitionsQueryTest extends IntegrationTestCase
{
    public function testOwnerSeesOwnCompetition(): void
    {
        $result = $this->queryBus()->handle(new ListMyCompetitions(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::VERIFIED_COMPETITION_ID, $result[0]->competitionId->toRfc4122());
        self::assertTrue($result[0]->isOwner);
    }

    public function testNonMemberSeesNothing(): void
    {
        $result = $this->queryBus()->handle(new ListMyCompetitions(
            userId: Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID),
        ));

        self::assertCount(0, $result);
    }
}
