<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Query\ListPendingJoinRequestsForCompetition\ListPendingJoinRequestsForCompetition;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ListPendingJoinRequestsForCompetitionQueryTest extends IntegrationTestCase
{
    public function testReturnsPendingFixtureRequest(): void
    {
        $result = $this->queryBus()->handle(new ListPendingJoinRequestsForCompetition(
            competitionId: Uuid::fromString(AppFixtures::PUBLIC_COMPETITION_ID),
        ));

        self::assertCount(1, $result);
        self::assertSame(AppFixtures::VERIFIED_USER_NICKNAME, $result[0]->nickname);
    }
}
