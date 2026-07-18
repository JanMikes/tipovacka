<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RegenerateShareableLink\RegenerateShareableLinkCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class RegenerateShareableLinkHandlerTest extends IntegrationTestCase
{
    public function testRegeneratesToken(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);

        $this->commandBus()->dispatch(new RegenerateShareableLinkCommand(
            ownerId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
        ));

        $em = $this->entityManager();
        $em->clear();

        $competition = $em->find(Competition::class, $competitionId);
        self::assertInstanceOf(Competition::class, $competition);
        self::assertNotNull($competition->shareableLinkToken);
        self::assertNotSame(AppFixtures::VERIFIED_COMPETITION_LINK_TOKEN, $competition->shareableLinkToken);
        self::assertSame(48, strlen($competition->shareableLinkToken));
    }
}
