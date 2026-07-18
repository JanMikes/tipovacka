<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class DashboardStatsFlowTest extends WebTestCase
{
    public function testMemberWithEvaluatedTipsSeesPersonalStats(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/nastenka');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Moje výsledky');
        // Populated branch renders the per-soutěž stat cards (not the empty hint).
        self::assertSelectorTextContains('body', 'POŘADÍ');
        self::assertSelectorTextContains('body', 'ÚSPĚŠNOST');
        self::assertSelectorTextContains('body', 'STREAK');
    }

    public function testMemberWithoutEvaluatedTipsSeesEmptyHint(): void
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
        self::assertSelectorTextContains('body', 'Moje výsledky');
        self::assertSelectorTextContains('body', 'Zatím bez výsledků');
    }
}
