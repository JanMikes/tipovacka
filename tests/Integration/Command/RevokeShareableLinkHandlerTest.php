<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RevokeShareableLink\RevokeShareableLinkCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class RevokeShareableLinkHandlerTest extends IntegrationTestCase
{
    public function testRevokesToken(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);

        $this->commandBus()->dispatch(new RevokeShareableLinkCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
        ));

        $em = $this->entityManager();
        $em->clear();

        $competition = $em->find(Competition::class, $competitionId);
        self::assertInstanceOf(Competition::class, $competition);
        self::assertNull($competition->shareableLinkToken);
    }
}
