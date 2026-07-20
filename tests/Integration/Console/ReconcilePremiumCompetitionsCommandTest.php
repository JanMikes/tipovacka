<?php

declare(strict_types=1);

namespace App\Tests\Integration\Console;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Enum\CompetitionMonetization;
use App\Event\PremiumConfirmed;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

/**
 * The host-cron console entry point {@see \App\Console\ReconcilePremiumCompetitionsCommand}
 * (`app:premium:reconcile`) must dispatch the premium reconciliation and produce
 * its side effect — mirrors the ReconcilePremiumCompetitionsHandlerTest scenario.
 */
final class ReconcilePremiumCompetitionsCommandTest extends IntegrationTestCase
{
    public function testCommandReconcilesPremiumCompetitionAtStart(): void
    {
        // PREMIUM_COMPETITION: single Charged row, start moment (kickoff 2025-06-10)
        // already passed vs the fixed clock ⇒ the sweep confirms it.
        $tester = $this->execute('app:premium:reconcile');

        $tester->assertCommandIsSuccessful();

        $competition = $this->entityManager()->find(Competition::class, Uuid::fromString(AppFixtures::PREMIUM_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);
        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);
        self::assertNotNull($competition->premiumReconciledAt);

        self::assertCount(1, $this->recordedDomainEvents()->ofType(PremiumConfirmed::class));
    }

    private function execute(string $name): CommandTester
    {
        // Reuse the already-booted kernel (idempotent — keeps the DAMA test
        // transaction and any prior setup dispatches intact; a re-boot would drop them).
        self::getContainer();
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $application = new Application($kernel);
        self::assertTrue($application->has($name), sprintf('Command "%s" must be registered.', $name));

        $tester = new CommandTester($application->find($name));
        $tester->execute([]);

        return $tester;
    }
}
