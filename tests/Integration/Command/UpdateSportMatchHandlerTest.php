<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\UpdateSportMatch\UpdateSportMatchCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateSportMatchHandlerTest extends IntegrationTestCase
{
    public function testUpdatesMatchFields(): void
    {
        $matchId = Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID);

        $this->commandBus()->dispatch(new UpdateSportMatchCommand(
            sportMatchId: $matchId,
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            homeTeam: 'Brno',
            awayTeam: null,
            kickoffAt: null,
            venue: 'Lužánky',
        ));

        $em = $this->entityManager();
        $em->clear();

        $match = $em->find(SportMatch::class, $matchId);
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame('Brno', $match->homeTeam);
        self::assertSame('Lužánky', $match->venue);
    }
}
