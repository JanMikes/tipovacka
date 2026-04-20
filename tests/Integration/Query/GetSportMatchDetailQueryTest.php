<?php

declare(strict_types=1);

namespace App\Tests\Integration\Query;

use App\DataFixtures\AppFixtures;
use App\Enum\SportMatchState;
use App\Query\GetSportMatchDetail\GetSportMatchDetail;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class GetSportMatchDetailQueryTest extends IntegrationTestCase
{
    public function testReturnsDetailForExistingMatch(): void
    {
        $result = $this->queryBus()->handle(new GetSportMatchDetail(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID),
        ));

        self::assertSame('Sparta Praha', $result->homeTeam);
        self::assertSame('Slavia Praha', $result->awayTeam);
        self::assertSame(SportMatchState::Scheduled, $result->state);
        self::assertTrue($result->isOpenForGuesses);
    }

    public function testFinishedMatchIsNotOpenForGuesses(): void
    {
        $result = $this->queryBus()->handle(new GetSportMatchDetail(
            sportMatchId: Uuid::fromString(AppFixtures::MATCH_FINISHED_ID),
        ));

        self::assertSame(SportMatchState::Finished, $result->state);
        self::assertFalse($result->isOpenForGuesses);
        self::assertSame(2, $result->homeScore);
        self::assertSame(1, $result->awayScore);
    }

    public function testThrowsWhenNotFound(): void
    {
        $this->expectException(HandlerFailedException::class);

        $this->queryBus()->handle(new GetSportMatchDetail(
            sportMatchId: Uuid::fromString('019ddddd-0000-7000-8000-000000099999'),
        ));
    }
}
