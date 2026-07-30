<?php

declare(strict_types=1);

namespace App\Tests\Integration\Public;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Item 05 — „Žebříček" (`/zebricek`) as a real, standalone, publicly viewable page.
 *
 * The acceptance criteria of the item, one test each; the authorization ones are the
 * point of the whole file: a public page over a voter that must NOT have been widened
 * beyond global competitions.
 */
final class PublicLeaderboardFlowTest extends WebTestCase
{
    public function testAnonymousVisitorSeesAPublicGlobalCompetitionsBoard(): void
    {
        $client = static::createClient();

        $client->request('GET', '/zebricek?soutez='.AppFixtures::GLOBAL_COMPETITION_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Žebříček');
        // A global competition (the only kind an anonymous visitor may reach). Item
        // 15 deleted the hero sub-text that used to link into it, so the board names
        // its soutěž through <title> and the switcher.
        self::assertSelectorTextContains('title', AppFixtures::GLOBAL_COMPETITION_NAME);
        // No „you" without a viewer.
        self::assertSelectorNotExists('.you-strip');
        // The logged-out hero CTA is registration, per item 01's nav decision.
        self::assertSelectorExists('a[href="/registrace"]');
    }

    /**
     * The nav entry carries no id, so the bare URL must land on something by itself.
     */
    public function testTheBareUrlPicksAGlobalCompetitionByItself(): void
    {
        $client = static::createClient();

        $client->request('GET', '/zebricek');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Žebříček');

        $title = $client->getCrawler()->filter('title')->text();
        $names = [AppFixtures::GLOBAL_COMPETITION_NAME, AppFixtures::FREE_GLOBAL_COMPETITION_NAME];
        $found = array_filter($names, static fn (string $name): bool => str_contains($title, $name));

        self::assertCount(1, $found, 'The default board must be one of the public global competitions.');
    }

    public function testAnonymousVisitorCannotReachAPrivateCompetitionByGuessingItsUuid(): void
    {
        $client = static::createClient();

        // VERIFIED_COMPETITION is a plain, non-global soutěž.
        $client->request('GET', '/zebricek?soutez='.AppFixtures::VERIFIED_COMPETITION_ID);

        // The page falls back to a board the visitor may see rather than 403-ing (the
        // nav has no id to offer), but nothing of the private soutěž may surface: no
        // name, no members, no link into it. Its id echoes only in <meta og:url>,
        // which is the request they made themselves.
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString(
            AppFixtures::VERIFIED_COMPETITION_NAME,
            (string) $client->getResponse()->getContent(),
        );
        self::assertSelectorNotExists('a[href="/souteze/'.AppFixtures::VERIFIED_COMPETITION_ID.'"]');
    }

    public function testAnonymousVisitorCannotReachTheTipRevealingSubPages(): void
    {
        $client = static::createClient();

        foreach ([
            '/zebricek/matice?soutez='.AppFixtures::GLOBAL_COMPETITION_ID,
            '/zebricek/clen/'.AppFixtures::ADMIN_ID.'?soutez='.AppFixtures::GLOBAL_COMPETITION_ID,
            '/zebricek/shoda?soutez='.AppFixtures::GLOBAL_COMPETITION_ID,
        ] as $path) {
            $client->request('GET', $path);

            self::assertResponseRedirects(message: $path.' must require a login.');
            self::assertStringContainsString(
                '/prihlaseni',
                (string) $client->getResponse()->headers->get('Location'),
                $path.' must bounce to the login page.',
            );
        }
    }

    public function testMemberSeesTheirOwnPositionAndIsHighlightedInTheTable(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/zebricek?soutez='.AppFixtures::VERIFIED_COMPETITION_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.you-strip');
        self::assertSelectorTextContains('.you-strip .lbl', 'Tvoje pozice');
        self::assertSelectorExists('tr.lb-tr.me');
        self::assertSelectorTextContains('tr.lb-tr.me .lb-ty', 'TY');
    }

    /**
     * A viewer looking at a public global competition they do NOT play in: the board is
     * theirs to read, but there is no „you", and the tip-revealing surfaces stay shut.
     */
    public function testNonMemberSeesAGlobalBoardWithoutTheirOwnStripOrTheTipSurfaces(): void
    {
        $client = static::createClient();
        $stranger = $this->createStranger($client);
        $client->loginUser($stranger);

        $client->request('GET', '/zebricek?soutez='.AppFixtures::GLOBAL_COMPETITION_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('title', AppFixtures::GLOBAL_COMPETITION_NAME);
        self::assertSelectorNotExists('.you-strip');
        self::assertSelectorNotExists('tr.lb-tr.me');
        // No per-member breakdown links and no „Tabulka tipů" — both are tip surfaces.
        self::assertSelectorNotExists('a[href^="/zebricek/clen/"]');
        self::assertSelectorNotExists('a[href^="/zebricek/matice"]');

        // …and asking for them directly is refused, not merely unlinked.
        $client->request('GET', '/zebricek/matice?soutez='.AppFixtures::GLOBAL_COMPETITION_ID);
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Item 15 stripped the hero back to the heading: no sub-text, no HRÁČŮ /
     * ODEHRÁNO / KOLO / AKTUALIZACE stats, and no filter card — one bare name
     * search, outside any card, and no period tabs, sort or expand control.
     */
    public function testTheHeroIsTheHeadingAndTheOnlyControlIsTheNameSearch(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', '/zebricek?soutez='.AppFixtures::PUBLIC_COMPETITION_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Žebříček');

        // The hero stats and the sub-heading text are gone.
        self::assertSelectorNotExists('.stat-lbl');
        self::assertSelectorTextNotContains('body', 'Odehráno');
        self::assertSelectorTextNotContains('body', 'Aktualizace');
        self::assertSelectorTextNotContains('body', 'Tipy ostatních tady nenajdeš');

        // No period tabs, no „Seřadit", no expand control — and no filter card.
        self::assertSelectorNotExists('.lb-tab');
        self::assertSelectorNotExists('.lb-toolbar');
        self::assertSelectorNotExists('select#lb-razeni');
        self::assertSelectorTextNotContains('body', 'Zobrazit celý žebříček');

        // The switcher form plus the search form, and nothing else.
        self::assertSelectorExists('input#lb-hledat');
        self::assertCount(2, $crawler->filter('form'));
    }

    /**
     * „Tvoje pozice" survives, minus its „Tipnout další zápas" button.
     */
    public function testTheYouStripKeepsNoTipButton(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/zebricek?soutez='.AppFixtures::VERIFIED_COMPETITION_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.you-strip');
        self::assertSelectorNotExists('.you-strip a');
        self::assertSelectorTextNotContains('.you-strip', 'Tipnout');
    }

    /**
     * A shared link from before item 15 must not 500 — the retired parameters are
     * simply not read any more.
     */
    public function testAStaleUrlCarryingTheRetiredParametersStillRenders(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::ADMIN_ID);

        $client->request(
            'GET',
            '/zebricek?soutez='.AppFixtures::PUBLIC_COMPETITION_ID.'&obdobi=7dni&razeni=body&vse=1',
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.lb-tab');
    }

    public function testSearchIsLinkableAndNarrowsTheTable(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request(
            'GET',
            '/zebricek?soutez='.AppFixtures::PUBLIC_COMPETITION_ID.'&hledat='.AppFixtures::ADMIN_NICKNAME,
        );

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('tr.lb-tr'));

        // A needle nobody matches renders the „not found" branch, not an empty table.
        $client->request('GET', '/zebricek?soutez='.AppFixtures::PUBLIC_COMPETITION_ID.'&hledat=zzz-nikdo');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'v tomhle žebříčku není');
    }

    /**
     * The search is a real GET form, so it filters with JavaScript off. Submitting
     * it through BrowserKit (which runs no JS at all) is that proof.
     */
    public function testTheSearchFormWorksWithoutJavaScript(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::ADMIN_ID);

        $crawler = $client->request('GET', '/zebricek?soutez='.AppFixtures::PUBLIC_COMPETITION_ID);
        self::assertResponseIsSuccessful();

        $searchForm = $crawler->filter('input#lb-hledat')->closest('form');
        self::assertNotNull($searchForm);
        $form = $searchForm->form();
        self::assertSame('GET', $form->getMethod());

        $form->setValues(['hledat' => 'zzz-nikdo']);
        $client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'v tomhle žebříčku není');
        self::assertStringContainsString('hledat=zzz-nikdo', $client->getRequest()->getUri());
        // The soutěž survives the submit — the hidden field carries it.
        self::assertStringContainsString(AppFixtures::PUBLIC_COMPETITION_ID, $client->getRequest()->getUri());
    }

    public function testTheSubPagesStillWorkForAMember(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::VERIFIED_USER_ID);

        foreach ([
            '/zebricek/matice?soutez='.AppFixtures::VERIFIED_COMPETITION_ID,
            '/zebricek/clen/'.AppFixtures::VERIFIED_USER_ID.'?soutez='.AppFixtures::VERIFIED_COMPETITION_ID,
        ] as $path) {
            $client->request('GET', $path);
            self::assertResponseIsSuccessful($path.' must render.');
        }

        // Without the soutěž a sub-page has nothing to scope to → 404, never a guess.
        $client->request('GET', '/zebricek/matice');
        self::assertResponseStatusCodeSame(404);
    }

    private function login(KernelBrowser $client, string $userId): void
    {
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString($userId));
        self::assertNotNull($user);
        $client->loginUser($user);
    }

    private function createStranger(KernelBrowser $client): User
    {
        $container = $client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $now = new \DateTimeImmutable('2025-06-15 12:00:00 UTC');
        $stranger = new User(
            id: Uuid::v7(),
            email: 'public-leaderboard-stranger@tipovacka.test',
            password: null,
            nickname: 'lb_public_'.bin2hex(random_bytes(3)),
            createdAt: $now,
        );
        $stranger->changePassword($hasher->hashPassword($stranger, 'password'), $now);
        $stranger->markAsVerified($now);
        $stranger->popEvents();
        $em->persist($stranger);
        $em->flush();

        return $stranger;
    }
}
