<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\UpdateCompetition\UpdateCompetitionCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateCompetitionHandlerTest extends IntegrationTestCase
{
    public function testUpdatesCompetitionDetails(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);

        $this->commandBus()->dispatch(new UpdateCompetitionCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
            name: 'Upravená parta',
            description: 'Nový popis',
            hideOthersTipsBeforeDeadline: false,
        ));

        $em = $this->entityManager();
        $em->clear();

        $competition = $em->find(Competition::class, $competitionId);
        self::assertInstanceOf(Competition::class, $competition);
        self::assertSame('Upravená parta', $competition->name);
        self::assertSame('Nový popis', $competition->description);
    }

    public function testPersistsTipVisibilitySettings(): void
    {
        $competitionId = Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID);

        $this->commandBus()->dispatch(new UpdateCompetitionCommand(
            editorId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
            competitionId: $competitionId,
            name: 'Parta',
            description: null,
            hideOthersTipsBeforeDeadline: true,
        ));

        $em = $this->entityManager();
        $em->clear();

        $competition = $em->find(Competition::class, $competitionId);
        self::assertInstanceOf(Competition::class, $competition);
        self::assertTrue($competition->hideOthersTipsBeforeDeadline);
        // Editing details never touches the tip lock.
        self::assertNull($competition->tipsLockedAt);
    }
}
