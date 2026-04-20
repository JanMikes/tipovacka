<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SoftDeleteSportMatch\SoftDeleteSportMatchCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class SoftDeleteSportMatchHandlerTest extends IntegrationTestCase
{
    public function testSoftDeletesMatch(): void
    {
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        $this->commandBus()->dispatch(new SoftDeleteSportMatchCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
        ));

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertNotNull($match->deletedAt);
    }
}
