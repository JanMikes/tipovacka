<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\UpdatePremiumSettings\UpdatePremiumSettingsCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Premium feature toggles + „Měnit tip" offset
 * ({@see \App\Command\UpdatePremiumSettings\UpdatePremiumSettingsHandler}).
 */
final class UpdatePremiumSettingsHandlerTest extends IntegrationTestCase
{
    public function testTogglesAndOffsetArePersisted(): void
    {
        $this->commandBus()->dispatch(new UpdatePremiumSettingsCommand(
            editorId: Uuid::fromString(AppFixtures::ADMIN_ID),
            competitionId: Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID),
            showDistribution: true,
            showOthersTips: true,
            allowTipChanges: true,
            tipChangeOffsetMinutes: 120,
        ));

        $em = $this->entityManager();
        $em->clear();

        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);
        self::assertTrue($competition->premiumShowDistribution);
        self::assertTrue($competition->premiumShowOthersTips);
        self::assertTrue($competition->premiumAllowTipChanges);
        self::assertSame(120, $competition->tipChangeOffsetMinutes);
    }
}
