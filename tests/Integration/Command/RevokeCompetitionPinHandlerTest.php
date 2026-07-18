<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RevokeCompetitionPin\RevokeCompetitionPinCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class RevokeCompetitionPinHandlerTest extends IntegrationTestCase
{
    public function testRevokesPin(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);

        $this->commandBus()->dispatch(new RevokeCompetitionPinCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
        ));

        $em = $this->entityManager();
        $em->clear();

        $competition = $em->find(Competition::class, $competitionId);
        self::assertInstanceOf(Competition::class, $competition);
        self::assertNull($competition->pin);
    }
}
