<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Leaderboard;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * S12 leaderboard-Δ UI surfaces. VERIFIED_COMPETITION has no finished matches, so
 * its live board is all-zeros; the seeded snapshots mirror that (0 points) — the
 * screen is self-consistent. VERIFIED_USER (owner) sits in the 2025-06-14 baseline
 * ⇒ „beze změny"; ANONYMOUS_USER joined today ⇒ absent from the baseline ⇒ „nový".
 */
final class LeaderboardDeltaFlowTest extends WebTestCase
{
    private const string DELTA_HEADER = 'th[title="Změna pořadí oproti předchozímu dennímu snímku"]';

    public function testLeaderboardRendersDeltaColumnCoherentlyWithSeededHistory(): void
    {
        $client = static::createClient();
        $this->loginVerified($client);

        $client->request('GET', '/zebricek?soutez='.AppFixtures::VERIFIED_COMPETITION_ID);

        self::assertResponseIsSuccessful();
        // The Δ column always renders — the board is all-time, so the daily
        // snapshot it is measured against always describes the same thing.
        self::assertSelectorExists(self::DELTA_HEADER);
        // ANONYMOUS_USER joined today ⇒ absent from the 2025-06-14 baseline ⇒ „nový".
        self::assertSelectorExists('.lb-delta-new');

        $body = (string) $client->getResponse()->getContent();
        // VERIFIED_USER was already rank 1 in the baseline ⇒ „Beze změny od minula".
        self::assertStringContainsString('Beze změny od minula', $body);
        // The factually-wrong „od včera" copy is retired everywhere on the page.
        self::assertStringNotContainsString('od včera', $body);
        self::assertStringNotContainsString('včerejšku', $body);
    }

    public function testDashboardMiniLeaderboardRendersDeltaChip(): void
    {
        $client = static::createClient();
        $this->loginVerified($client);

        $client->request('GET', '/nastenka');

        self::assertResponseIsSuccessful();
        // The mini-board carries the Δ chip; ANONYMOUS_USER is „nový" (absent from
        // the previous snapshot day) ⇒ the „nový" chip variant renders.
        self::assertSelectorExists('.lb-delta-chip.new');
    }

    public function testMemberBreakdownRendersProgressList(): void
    {
        $client = static::createClient();
        $this->loginVerified($client);

        $client->request(
            'GET',
            '/zebricek/clen/'.AppFixtures::VERIFIED_USER_ID.'?soutez='.AppFixtures::VERIFIED_COMPETITION_ID,
        );

        self::assertResponseIsSuccessful();
        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Vývoj', $body);
        // Both seeded snapshot days appear (2025-06-14 baseline and 2025-06-15).
        self::assertStringContainsString('14. 6. 2025', $body);
        self::assertStringContainsString('15. 6. 2025', $body);
    }

    private function loginVerified(KernelBrowser $client): void
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $client->loginUser($verified);
    }
}
