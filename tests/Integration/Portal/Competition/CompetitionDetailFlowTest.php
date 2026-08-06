<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Competition;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class CompetitionDetailFlowTest extends WebTestCase
{
    public function testOwnerCanViewOwnCompetition(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('GET', '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', AppFixtures::VERIFIED_COMPETITION_NAME);
    }

    public function testAdminCanViewAnyCompetition(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertResponseIsSuccessful();
    }

    /**
     * The members list moved to „Nastavení soutěže" with item 08 — the detail page
     * is a playing surface now. The rendering contract itself is unchanged.
     */
    public function testMembersListShowsFullNameSubtitleWhenBothPresent(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');

        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $owner->updateProfile(firstName: 'Jan', lastName: 'Tipař', phone: null, now: new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $em->flush();

        $client->loginUser($owner);

        $client->request('GET', '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'/nastaveni');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        self::assertIsString($body);

        // Verified owner has nickname + fullName → subtitle rendered.
        self::assertStringContainsString(AppFixtures::VERIFIED_USER_NICKNAME, $body);
        self::assertMatchesRegularExpression(
            '#<small[^>]*>\s*Jan Tipař\s*</small>#u',
            $body,
            'Nickname+fullName user shows <small>Jan Tipař</small> subtitle.',
        );

        // Anonymous member (no nickname) shows fullName as primary, no <small> subtitle for them.
        self::assertStringContainsString(AppFixtures::ANONYMOUS_USER_FIRST_NAME, $body);
        self::assertDoesNotMatchRegularExpression(
            '#<small[^>]*>\s*'.preg_quote(AppFixtures::ANONYMOUS_USER_FIRST_NAME.' '.AppFixtures::ANONYMOUS_USER_LAST_NAME, '#').'\s*</small>#u',
            $body,
            'No subtitle for fullName-only member.',
        );
    }

    /**
     * Item 08 — the detail page is a playing surface: „Členové", „Pravidla" and
     * „Správa" are gone from it, and the four-item action bar leads to their new
     * home instead.
     */
    public function testOrganizerSeesTheActionBarAndNoneOfTheRelocatedBlocks(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $id = AppFixtures::VERIFIED_COMPETITION_ID;
        $crawler = $client->request('GET', '/souteze/'.$id);
        self::assertResponseIsSuccessful();

        self::assertCount(1, $crawler->filter('a[href="/souteze/'.$id.'/nastaveni"]'), 'Nastavení');
        // Back to one: the action bar's „Pozvat" used to lead into „Nastavení →
        // Pozvánky", but inviting is a player's action now, so it opens the
        // „Pozvat přítele" card instead (an in-page anchor). The only link left into
        // the organizer's invitation block is the one inside that card's dialog.
        self::assertCount(1, $crawler->filter('a[href="/souteze/'.$id.'/nastaveni#pozvanky"]'), 'Spravovat pozvánky');
        self::assertCount(1, $crawler->filter('a[href="#pozvat"]'), 'Pozvat přítele → the card');
        self::assertSelectorTextContains('body', 'Pozvat přítele');
        self::assertCount(1, $crawler->filter('a[href="/souteze/'.$id.'/spravovat-tipy"]'), 'Tipovat za členy');
        self::assertCount(1, $crawler->filter('form[action="/souteze/'.$id.'/uzamknout-tipy"]'), 'Uzamknout tipy');

        // The banner the product owner asked to keep above the match list.
        self::assertCount(1, $crawler->filter('a[href="/souteze/'.$id.'/moje-tipy"]'));
        self::assertSelectorTextContains('body', 'Tipněte si všechny zápasy najednou');

        // Relocated blocks are gone.
        $body = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('Rychlé pozvánky', $body);
        self::assertStringNotContainsString('Pozvánky e-mailem', $body);
        self::assertCount(0, $crawler->filter('form[action="/souteze/'.$id.'/smazat"]'));
        self::assertCount(0, $crawler->filter('form[action="/souteze/'.$id.'/pin/novy"]'));

        // The žebříček panel carries real rows now, not just a CTA.
        self::assertGreaterThanOrEqual(1, $crawler->filter('.lb-row')->count());
    }

    public function testPlainMemberGetsNoActionBar(): void
    {
        // SECOND_VERIFIED_USER is a plain member of the boosts competition (ADMIN owns it).
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $member = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($member);
        $client->loginUser($member);

        $id = AppFixtures::BOOSTS_COMPETITION_ID;
        $crawler = $client->request('GET', '/souteze/'.$id);
        self::assertResponseIsSuccessful();

        self::assertCount(0, $crawler->filter('a[href="/souteze/'.$id.'/nastaveni"]'));
        self::assertCount(0, $crawler->filter('a[href="/souteze/'.$id.'/spravovat-tipy"]'));
        self::assertCount(0, $crawler->filter('form[action="/souteze/'.$id.'/uzamknout-tipy"]'));
    }

    /**
     * Inviting is NOT an organizer privilege: a plain member gets the join link (and
     * the PIN, where one exists) AND the e-mail invitation form, so they can pull their
     * friends in themselves. What stays organizer-only is the CONTROL — regenerating and
     * revoking the PIN/link, and the bulk paste-an-address-book form.
     */
    public function testPlainMemberCanShareTheJoinLink(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $member = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($member);
        $client->loginUser($member);

        $id = AppFixtures::BOOSTS_COMPETITION_ID;
        $crawler = $client->request('GET', '/souteze/'.$id);
        self::assertResponseIsSuccessful();

        $body = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Pozvat přítele', $body);
        self::assertStringContainsString(AppFixtures::BOOSTS_COMPETITION_LINK_TOKEN, $body);
        // The e-mail invitation form is theirs too, and it posts back to the competition
        // page — a plain member has no „Nastavení" to be redirected to.
        self::assertCount(1, $crawler->filter('form[action="/souteze/'.$id.'/pozvanky/odeslat"]'));
        self::assertCount(1, $crawler->filter('form[action="/souteze/'.$id.'/pozvanky/odeslat"] input[name="navrat"][value="detail"]'));
        // …but none of the controls that regenerate or revoke it, no bulk form, and no
        // way into the organizer's invitation block.
        self::assertCount(0, $crawler->filter('form[action="/souteze/'.$id.'/odkaz/novy"]'));
        self::assertCount(0, $crawler->filter('form[action="/souteze/'.$id.'/pin/novy"]'));
        self::assertCount(0, $crawler->filter('form[action="/souteze/'.$id.'/pozvanky/hromadne"]'));
        self::assertCount(0, $crawler->filter('a[href="/souteze/'.$id.'/nastaveni#pozvanky"]'));
    }

    public function testGlobalCompetitionHidesTipsOnBehalf(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $id = AppFixtures::GLOBAL_COMPETITION_ID;
        $crawler = $client->request('GET', '/souteze/'.$id);
        self::assertResponseIsSuccessful();

        self::assertCount(0, $crawler->filter('a[href="/souteze/'.$id.'/spravovat-tipy"]'));
        // A global competition has no PIN and no shareable link to manage, so the way
        // into „Nastavení → Pozvánky" stays shut — even for its admin owner…
        self::assertCount(0, $crawler->filter('a[href="/souteze/'.$id.'/nastaveni#pozvanky"]'));
        // …but inviting itself is open, and what it hands over is the competition's own
        // public invitation page, where the invitee pays the entry fee.
        self::assertSelectorTextContains('body', 'Pozvat přítele');
        self::assertStringContainsString('/souteze/'.$id.'/pozvanka', (string) $client->getResponse()->getContent());
    }

    public function testNonMemberReceivesForbidden(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $stranger = new User(
            id: Uuid::v7(),
            email: 'stranger@tipovacka.test',
            password: null,
            nickname: 'stranger_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $stranger->changePassword($hasher->hashPassword($stranger, 'password'), $now);
        $stranger->markAsVerified($now);
        $stranger->popEvents();
        $em->persist($stranger);
        $em->flush();

        $client->loginUser($stranger);

        $client->request('GET', '/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID);
        self::assertResponseStatusCodeSame(403);
    }
}
