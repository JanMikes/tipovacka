<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\Competition;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class CompetitionLockTipsFlowTest extends WebTestCase
{
    private const string DETAIL_URL = '/portal/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID;
    private const string LOCK_URL = self::DETAIL_URL.'/uzamknout-tipy';
    private const string UNLOCK_URL = self::DETAIL_URL.'/odemknout-tipy';

    public function testOwnerSeesLockButtonWithConfirmModalAndDeadlineInHero(): void
    {
        $client = static::createClient();
        $owner = $this->loginOwner($client);

        $crawler = $client->request('GET', self::DETAIL_URL);
        self::assertResponseIsSuccessful();

        // Hero shows the competition-level lock moment (first kickoff
        // 2025-06-20 19:00 UTC = 21:00 Europe/Prague) while still open.
        self::assertSelectorTextContains('body', 'Uzávěrka tipů: 20. 6. 2025 21:00');
        self::assertSelectorTextNotContains('body', 'Tipy uzamčeny');

        // The lock form goes through the confirm modal (Stimulus confirm controller).
        $form = $crawler->filter('form[action="'.self::LOCK_URL.'"]');
        self::assertCount(1, $form);
        self::assertSame('confirm', $form->attr('data-controller'));
        self::assertSame('Uzamknout tipy', $form->attr('data-confirm-title-value'));
        self::assertNotNull($form->attr('data-confirm-message-value'));
        self::assertCount(1, $form->filter('input[name="_token"]'));
    }

    public function testLockFlowLocksAndShowsUnlockButton(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $this->loginOwner($client);

        $crawler = $client->request('GET', self::DETAIL_URL);
        $client->submit($crawler->filter('form[action="'.self::LOCK_URL.'"]')->form());

        self::assertResponseRedirects(self::DETAIL_URL);
        $crawler = $client->followRedirect();

        // Locked: pill in the hero, unlock button offered (first kickoff still ahead).
        self::assertSelectorTextContains('body', 'Tipy uzamčeny');
        self::assertSelectorTextContains('body', 'Tipy byly uzamčeny.');
        self::assertSelectorTextNotContains('body', 'Uzávěrka tipů:');
        self::assertCount(0, $crawler->filter('form[action="'.self::LOCK_URL.'"]'));
        self::assertCount(1, $crawler->filter('form[action="'.self::UNLOCK_URL.'"]'));

        $em->clear();
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);
        self::assertNotNull($competition->tipsLockedAt);
    }

    public function testUnlockFlowReopensTipping(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $this->loginOwner($client);

        $crawler = $client->request('GET', self::DETAIL_URL);
        $client->submit($crawler->filter('form[action="'.self::LOCK_URL.'"]')->form());
        $crawler = $client->followRedirect();

        $client->submit($crawler->filter('form[action="'.self::UNLOCK_URL.'"]')->form());
        self::assertResponseRedirects(self::DETAIL_URL);
        $crawler = $client->followRedirect();

        self::assertSelectorTextContains('body', 'Tipy byly odemčeny.');
        self::assertSelectorTextContains('body', 'Uzávěrka tipů: 20. 6. 2025 21:00');
        self::assertCount(1, $crawler->filter('form[action="'.self::LOCK_URL.'"]'));
        self::assertCount(0, $crawler->filter('form[action="'.self::UNLOCK_URL.'"]'));

        $em->clear();
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);
        self::assertNull($competition->tipsLockedAt);
    }

    public function testUnlockRaceAfterFirstKickoffSurfacesFlashNotErrorPage(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $this->loginOwner($client);

        // Lock VERIFIED_COMPETITION (first kickoff 2025-06-20 still ahead ⇒ unlock offered).
        $crawler = $client->request('GET', self::DETAIL_URL);
        $client->submit($crawler->filter('form[action="'.self::LOCK_URL.'"]')->form());
        $crawler = $client->followRedirect();
        $unlockForm = $crawler->filter('form[action="'.self::UNLOCK_URL.'"]')->form();

        // Race: the first match kicks off between rendering the button and this POST.
        // Keep the kernel (and its MockClock) alive across the POST so the advance sticks.
        $client->disableReboot();
        /** @var ClockInterface $clock */
        $clock = $client->getContainer()->get(ClockInterface::class);
        self::assertInstanceOf(MockClock::class, $clock);
        $clock->modify('+10 days'); // now 2025-06-25, past the 06-20 kickoff

        $client->submit($unlockForm);

        // Surfaced as a flash + redirect (mirrors siblings), NOT a 409 error page.
        self::assertResponseRedirects(self::DETAIL_URL);
        $crawler = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Tipy už nelze odemknout, soutěž již začala.');

        $em->clear();
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertInstanceOf(Competition::class, $competition);
        // The lock survived — the unlock was rejected.
        self::assertNotNull($competition->tipsLockedAt);
    }

    public function testAutoLockedStartedCompetitionShowsPillWithoutUnlockButton(): void
    {
        // SUBSET_COMPETITION's first included match (MATCH_FINISHED) kicked off
        // 2025-06-10 ⇒ auto-locked. There is no manual lock to undo, so neither
        // the lock nor the unlock button may render.
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $detailUrl = '/portal/souteze/'.AppFixtures::SUBSET_COMPETITION_ID;
        $crawler = $client->request('GET', $detailUrl);
        self::assertResponseIsSuccessful();

        self::assertSelectorTextContains('body', 'Tipy uzamčeny');
        self::assertCount(0, $crawler->filter('form[action="'.$detailUrl.'/uzamknout-tipy"]'));
        self::assertCount(0, $crawler->filter('form[action="'.$detailUrl.'/odemknout-tipy"]'));
    }

    public function testMemberWithoutEditRightsSeesNoLockButton(): void
    {
        // Add the second verified user as a plain member of VERIFIED_COMPETITION.
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $member = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($member);
        $competition = $em->find(Competition::class, Uuid::fromString(AppFixtures::VERIFIED_COMPETITION_ID));
        self::assertNotNull($competition);

        $membership = new \App\Entity\Membership(
            id: Uuid::v7(),
            competition: $competition,
            user: $member,
            joinedAt: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'),
        );
        $membership->popEvents();
        $em->persist($membership);
        $em->flush();

        $client->loginUser($member);

        $crawler = $client->request('GET', self::DETAIL_URL);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('form[action="'.self::LOCK_URL.'"]'));
    }

    private function loginOwner(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): User
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        return $owner;
    }
}
