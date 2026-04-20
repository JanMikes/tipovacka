<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SetSportMatchFinalScore\SetSportMatchFinalScoreCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class SetSportMatchFinalScoreHandlerTest extends IntegrationTestCase
{
    public function testFinalizesMatchWithScore(): void
    {
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SetSportMatchFinalScoreCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeScore: 3,
            awayScore: 1,
        ));

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Finished, $match->state);
        self::assertSame(3, $match->homeScore);
        self::assertSame(1, $match->awayScore);
    }
}
