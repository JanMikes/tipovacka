<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\PostponeSportMatch\PostponeSportMatchCommand;
use App\Command\RescheduleSportMatch\RescheduleSportMatchCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class RescheduleSportMatchHandlerTest extends IntegrationTestCase
{
    public function testReschedulesPostponedMatchBackToScheduled(): void
    {
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);
        $editorId = Uuid::fromString(AppFixtures::ADMIN_ID);

        $this->commandBus()->dispatch(new PostponeSportMatchCommand(
            sportMatchId: $matchId,
            editorId: $editorId,
            newKickoffAt: new \DateTimeImmutable('2025-07-30 18:00:00 UTC'),
        ));

        $newKickoff = new \DateTimeImmutable('2025-08-05 20:00:00 UTC');
        $this->commandBus()->dispatch(new RescheduleSportMatchCommand(
            sportMatchId: $matchId,
            editorId: $editorId,
            newKickoffAt: $newKickoff,
        ));

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Scheduled, $match->state);
        self::assertEquals($newKickoff, $match->kickoffAt);
    }
}
