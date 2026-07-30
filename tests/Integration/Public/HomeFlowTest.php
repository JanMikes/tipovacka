<?php

declare(strict_types=1);

namespace App\Tests\Integration\Public;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class HomeFlowTest extends WebTestCase
{
    public function testAnonymousSeesLandingPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tipuj');
        self::assertSelectorExists('a[href="/registrace"]');
    }

    public function testLandingPageClosesWithAnEvergreenCallToAction(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('.surface-accent'), 'Exactly one accent-card CTA.');

        $cta = $crawler->filter('section')->last()->filter('.surface-accent');
        self::assertCount(1, $cta, 'The landing page must end with the accent-card CTA.');
        self::assertSame(
            'Registrace zdarma',
            trim($cta->filter('a.btn')->text()),
            'An anonymous visitor gets the register action.',
        );
        self::assertSame('/registrace', $cta->filter('a.btn')->attr('href'));
    }

    /**
     * The banner this CTA replaced was tied to a tournament that has finished. Nothing on the
     * marketing page may carry a tournament name or a year again.
     */
    public function testLandingPageCarriesNoExpiringTournamentCopy(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringNotContainsStringIgnoringCase('MS 2026', $html);
        self::assertStringNotContainsStringIgnoringCase('ms-2026', $html);
    }

    public function testAuthenticatedRedirectedToDashboard(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/');

        self::assertResponseRedirects('/nastenka');
    }
}
