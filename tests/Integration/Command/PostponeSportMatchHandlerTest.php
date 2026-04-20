<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\PostponeSportMatch\PostponeSportMatchCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Enum\SportMatchState;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class PostponeSportMatchHandlerTest extends IntegrationTestCase
{
    public function testPostponesScheduledMatch(): void
    {
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);
        $newKickoff = new \DateTimeImmutable('2025-07-30 18:00:00 UTC');

        $this->commandBus()->dispatch(new PostponeSportMatchCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            newKickoffAt: $newKickoff,
        ));

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame(SportMatchState::Postponed, $match->state);
        self::assertEquals($newKickoff, $match->kickoffAt);
    }
}
