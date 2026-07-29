<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The „Tvoje pozice" hero card (item 06) — the one place the Nástěnka states the
 * viewer's standing in the soutěž in focus.
 */
final class DashboardStatsFlowTest extends WebTestCase
{
    public function testMemberWithEvaluatedTipsSeesTheStandingCard(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        // Explicitly select PUBLIC_COMPETITION (where admin has an evaluated tip).
        $client->request('GET', '/nastenka?soutez='.AppFixtures::PUBLIC_COMPETITION_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.hero-rank-label', 'Tvoje pozice');
        self::assertSelectorTextContains('.hero-rank-pool', AppFixtures::PUBLIC_COMPETITION_NAME);
        self::assertSelectorTextContains('.hero-rank-num', '.');
        self::assertSelectorTextContains('.hero-rank-meta', 'Body');
        self::assertSelectorTextContains('.hero-rank-meta', 'Změna');
    }

    public function testMemberWithoutEvaluatedTipsStillSeesTheirRank(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        // The verified user belongs to VERIFIED_COMPETITION, which has no evaluated tips.
        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $client->loginUser($verified);

        $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.hero-rank-pool', AppFixtures::VERIFIED_COMPETITION_NAME);
        // Nothing evaluated yet ⇒ „Úspěšnost —", never a fabricated percentage.
        self::assertSelectorTextContains('.hero-rank-meta', 'Úspěšnost');
        self::assertSelectorTextContains('body', 'Zatím nemáš žádný vyhodnocený tip');
    }
}
