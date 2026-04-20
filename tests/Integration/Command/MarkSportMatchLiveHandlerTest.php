<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\MarkSportMatchLive\MarkSportMatchLiveCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class MarkSportMatchLiveHandlerTest extends IntegrationTestCase
{
    public function testMarksScheduledMatchAsLive(): void
    {
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        $this->commandBus()->dispatch(new MarkSportMatchLiveCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Live, $match->state);
    }
}
