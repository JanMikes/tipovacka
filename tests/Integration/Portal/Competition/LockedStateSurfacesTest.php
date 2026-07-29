<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * B5 — locking must be a visible, persistent state rather than a one-shot flash.
 * Every surface that offers tipping has to switch to its locked variant, and an
 * absent tip has to read „Netipováno" (a fact) instead of „Nevyplněno" (a call to
 * action) or an inviting empty form.
 *
 * The lock is applied at the MockClock's now (2025-06-15 12:00 UTC = 14:00 Prague);
 * fixture matches are created at exactly that instant, so none of them counts as
 * late-added and the whole competition really closes.
 */
final class LockedStateSurfacesTest extends WebTestCase
{
    private const string DETAIL_URL = '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID;
    private const string BATCH_URL = self::DETAIL_URL.'/moje-tipy';
    private const string MANAGE_TIPS_URL = self::DETAIL_URL.'/spravovat-tipy';
    private const string MATCH_DETAIL_URL = '/zapasy/'.AppFixtures::MATCH_PRIVATE_SCHEDULED_ID;

    public function testOpenCompetitionInvitesToTipAndSaysNothingAboutALock(): void
    {
        $client = static::createClient();
        $this->login($client);

        $crawler = $client->request('GET', self::DETAIL_URL);
        self::assertResponseIsSuccessful();

        self::assertSelectorTextNotContains('body', 'Tipy uzamčeny');
        self::assertSelectorTextNotContains('body', 'Tipování uzavřeno');
        self::assertCount(1, $crawler->filter('a[href="'.self::BATCH_URL.'"]'));

        // An unfilled tip in an OPEN competition is a call to action.
        $client->request('GET', self::MATCH_DETAIL_URL);
        self::assertSelectorTextContains('body', 'Nevyplněno');
        self::assertSelectorTextNotContains('body', 'Netipováno');
    }

    public function testDetailHeaderShowsTheLockWithItsEffectiveMoment(): void
    {
        $client = static::createClient();
        $this->login($client);
        $this->lockTips($client);

        $crawler = $client->request('GET', self::DETAIL_URL);
        self::assertResponseIsSuccessful();

        // Pilulka (item 08) + the moment it took effect (B5), in Prague time.
        self::assertSelectorTextContains('body', 'Tipy uzamčeny 15. 6. 2025 14:00');
        // Nothing is tippable any more ⇒ no „u některých zápasů" caveat.
        self::assertSelectorTextNotContains('body', 'u některých zápasů je tipování stále otevřené');

        // The „tipněte si všechno najednou" invitation is replaced by the state.
        self::assertCount(0, $crawler->filter('a[href="'.self::BATCH_URL.'"]'));
        self::assertSelectorTextContains('body', 'Tipování uzavřeno');
        self::assertSelectorTextContains('body', 'Tipy se uzamkly 15. 6. 2025 14:00');
    }

    public function testBatchTipsPageExplainsTheLockInsteadOfClaimingThereAreNoMatches(): void
    {
        $client = static::createClient();
        $this->login($client);
        $this->lockTips($client);

        $client->request('GET', self::BATCH_URL);
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('body', 'Tipování uzavřeno');
        self::assertSelectorTextContains('body', 'Tipy se uzamkly 15. 6. 2025 14:00');
        self::assertSelectorTextNotContains('body', 'Žádné nadcházející zápasy k tipování.');
    }

    public function testOnBehalfTipsPageExplainsTheLock(): void
    {
        $client = static::createClient();
        $this->login($client);
        $this->lockTips($client);

        $client->request('GET', self::MANAGE_TIPS_URL.'?member='.AppFixtures::ANONYMOUS_USER_ID);
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('body', 'Tipování uzavřeno');
        self::assertSelectorTextContains('body', 'Za členy už tipovat nejde.');
        self::assertSelectorTextNotContains('body', 'Žádné nadcházející zápasy k tipování.');
    }

    public function testMatchDetailSaysNetipovanoRatherThanAskingForATip(): void
    {
        $client = static::createClient();
        $this->login($client);
        $this->lockTips($client);

        $crawler = $client->request('GET', self::MATCH_DETAIL_URL);
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('body', 'Netipováno');
        self::assertSelectorTextNotContains('body', 'Nevyplněno');
        // The card no longer wears the „you still owe a tip" accent ring.
        self::assertCount(0, $crawler->filter('article.ring-accent-400\\/60'));
        // …and the tip form itself renders its locked variant, not inputs.
        self::assertSelectorTextContains('body', 'Tipování uzavřeno — uzávěrka proběhla 15. 6. 2025 14:00.');
    }

    private function lockTips(KernelBrowser $client): void
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);
        $competition->lockTips(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $competition->popEvents();
        $em->flush();
    }

    private function login(KernelBrowser $client): void
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user);
    }
}
