<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Enum\CompetitionMonetization;
use App\Tests\Support\WebFlowHelpers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * „Prémium na nás" as an admin actually uses it: the button on /admin/souteze.
 * The billing rules themselves are pinned by
 * {@see \App\Tests\Integration\Command\SponsorCompetitionPremiumHandlerTest}.
 */
final class AdminSponsorPremiumFlowTest extends WebTestCase
{
    use WebFlowHelpers;

    public function testAdminGrantsAndThenWithdrawsPremiumFromTheList(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::ADMIN_ID);

        $client->request('GET', '/admin/souteze');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Prémium na nás');

        $this->post($client, AppFixtures::VERIFIED_COMPETITION_ID);

        self::assertResponseRedirects('/admin/souteze');
        $competition = $this->competition($client, AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertTrue($competition->isPremiumSponsored);
        self::assertSame(CompetitionMonetization::Premium, $competition->monetization);

        // The same button now reads as the withdrawal and toggles back.
        $this->post($client, AppFixtures::VERIFIED_COMPETITION_ID);

        self::assertFalse($this->competition($client, AppFixtures::VERIFIED_COMPETITION_ID)->isPremiumSponsored);
    }

    /** The gift writes off real money — it is not reachable without ROLE_ADMIN. */
    public function testAnOrdinaryUserCannotSponsorTheirOwnCompetition(): void
    {
        $client = static::createClient();
        $this->loginUserById($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('POST', sprintf('/admin/souteze/%s/sponzorovat-premium', AppFixtures::VERIFIED_COMPETITION_ID));

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->competition($client, AppFixtures::VERIFIED_COMPETITION_ID)->isPremiumSponsored);
    }

    /**
     * Posts the row's real form, with the token the page actually rendered —
     * so the CSRF guard is exercised rather than bypassed.
     */
    private function post(KernelBrowser $client, string $competitionId): void
    {
        $action = sprintf('/admin/souteze/%s/sponzorovat-premium', $competitionId);

        $crawler = $client->request('GET', '/admin/souteze');
        $token = $crawler
            ->filterXPath(sprintf('//form[@action="%s"]//input[@name="_token"]', $action))
            ->attr('value');

        self::assertIsString($token);

        $client->request('POST', $action, ['_token' => $token]);
    }

    private function competition(KernelBrowser $client, string $competitionId): Competition
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        $competition = $entityManager->find(Competition::class, Uuid::fromString($competitionId));
        self::assertInstanceOf(Competition::class, $competition);

        return $competition;
    }
}
