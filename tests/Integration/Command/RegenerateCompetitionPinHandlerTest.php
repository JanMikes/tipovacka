<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RegenerateCompetitionPin\RegenerateCompetitionPinCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class RegenerateCompetitionPinHandlerTest extends IntegrationTestCase
{
    public function testRegeneratesPin(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);

        $this->commandBus()->dispatch(new RegenerateCompetitionPinCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
        ));

        $em = $this->entityManager();
        $em->clear();

        $competition = $em->find(Competition::class, $competitionId);
        self::assertInstanceOf(Competition::class, $competition);
        self::assertNotNull($competition->pin);
        self::assertNotSame(AppFixtures::VERIFIED_COMPETITION_PIN, $competition->pin);
        self::assertMatchesRegularExpression('/^\d{8}$/', $competition->pin);
    }
}
