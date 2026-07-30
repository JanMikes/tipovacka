<?php

declare(strict_types=1);

namespace App\Tests\Integration\Public;

use App\Command\MarkMatchSourceCompleted\MarkMatchSourceCompletedCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\Sport;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * `/souteze` — the context-aware „Soutěže" page (item 07). The route is public;
 * the member-only sections degrade away for an anonymous visitor rather than
 * gating the whole page.
 */
final class CompetitionsListFlowTest extends WebTestCase
{
    public function testAnonymousSeesGlobalCompetitions(): void
    {
        $client = static::createClient();
        $client->request('GET', '/souteze');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', AppFixtures::GLOBAL_COMPETITION_NAME);
        self::assertSelectorTextContains('body', AppFixtures::FREE_GLOBAL_COMPETITION_NAME);
        // Anonymous visitor is prompted to log in.
        self::assertSelectorTextContains('body', 'Přihlásit se a připojit');
    }

    public function testAnonymousGetsNeitherMemberNorOrganizerSection(): void
    {
        $client = static::createClient();
        $client->request('GET', '/souteze');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#souteze-hraju');
        self::assertSelectorNotExists('#souteze-organizuji');
        // The PIN bar, though, is here on purpose since B15: a PIN is how most people
        // arrive, and it no longer needs an account — it remembers the soutěž through
        // sign-up instead of bouncing off a login wall.
        self::assertSelectorExists('form[action="/pripojit/rychle"]');
    }

    public function testNonGlobalCompetitionsAreNotListed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/souteze');

        self::assertResponseIsSuccessful();
        // PUBLIC_COMPETITION ("Admin liga") is not global ⇒ not discoverable.
        self::assertSelectorTextNotContains('body', AppFixtures::PUBLIC_COMPETITION_NAME);
    }

    public function testVerifiedNonMemberSeesJoinButton(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/souteze');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action$="/pripojit-se"]');
    }

    public function testInsufficientCreditsShowsTopUpStateInsteadOfJoinButton(): void
    {
        $client = static::createClient();
        // VERIFIED_USER has no wallet ⇒ 0 credits; the paid global costs 50.
        $this->login($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/souteze');

        self::assertResponseIsSuccessful();
        // Upfront „Máte 0/50 kreditů — dokoupit" state, NOT a bare join button that
        // would bounce to the top-up page.
        self::assertSelectorTextContains('body', 'Máte 0/'.AppFixtures::GLOBAL_COMPETITION_ENTRY_FEE.' kreditů');
        self::assertSelectorNotExists('form[action="/souteze/'.AppFixtures::GLOBAL_COMPETITION_ID.'/pripojit-se"]');
        // The free global still offers a direct join.
        self::assertSelectorExists('form[action="/souteze/'.AppFixtures::FREE_GLOBAL_COMPETITION_ID.'/pripojit-se"]');
    }

    public function testFinishedSourceExcludesGlobalCompetitions(): void
    {
        $client = static::createClient();
        /** @var MessageBusInterface $commandBus */
        $commandBus = $client->getContainer()->get('test.command.bus');
        $commandBus->dispatch(new MarkMatchSourceCompletedCommand(
            matchSourceId: Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID),
        ));

        $client->request('GET', '/souteze');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', AppFixtures::GLOBAL_COMPETITION_NAME);
    }

    public function testMemberSeesTheirOwnCompetitionsAndTheOrganizerSection(): void
    {
        $client = static::createClient();
        // VERIFIED_USER plays in AND owns „Kámoši u piva".
        $this->login($client, AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/souteze');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#souteze-hraju');
        self::assertSelectorExists('#souteze-organizuji');
        self::assertSelectorTextContains('#souteze-hraju', AppFixtures::VERIFIED_COMPETITION_NAME);
        self::assertSelectorTextContains('#souteze-organizuji', AppFixtures::VERIFIED_COMPETITION_NAME);
        self::assertSelectorTextContains('#souteze-organizuji', 'Spravovat');
    }

    public function testOrganizerSectionIsAbsentForSomeoneWhoOrganizesNothing(): void
    {
        $client = static::createClient();
        // ANONYMOUS_USER is a member of VERIFIED_COMPETITION but owns nothing.
        $this->login($client, AppFixtures::ANONYMOUS_USER_ID);

        $client->request('GET', '/souteze');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#souteze-hraju');
        self::assertSelectorNotExists('#souteze-organizuji');
    }

    public function testFiltersAreQueryParamDrivenAndTheCountFollows(): void
    {
        $client = static::createClient();

        $client->request('GET', '/souteze');
        self::assertSelectorTextContains('#souteze-verejne', '2 z 2 soutěží');

        // Every fixture competition is football ⇒ the hockey filter empties the list
        // while the „z N" total keeps describing the unfiltered scope.
        $client->request('GET', '/souteze?sport='.Sport::HOCKEY_ID);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#souteze-verejne', '0 z 2 soutěží');
        self::assertSelectorTextNotContains('#souteze-verejne', AppFixtures::GLOBAL_COMPETITION_NAME);

        // …and a name search narrows it to exactly one.
        $client->request('GET', '/souteze?hledat=zdarma');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#souteze-verejne', '1 z 2 soutěží');
        self::assertSelectorTextContains('#souteze-verejne', AppFixtures::FREE_GLOBAL_COMPETITION_NAME);
    }

    public function testTheTwoFilterBarsDoNotDisturbEachOther(): void
    {
        $client = static::createClient();
        $this->login($client, AppFixtures::VERIFIED_USER_ID);

        // The organizer bar filters by its own prefixed params only.
        $client->request('GET', '/souteze?moje-viditelnost=verejne');

        self::assertResponseIsSuccessful();
        // „Kámoši u piva" is not global ⇒ filtered out of the organizer grid…
        self::assertSelectorTextContains('#souteze-organizuji', '0 z 1');
        // …while the public grid is untouched by that parameter.
        self::assertSelectorTextContains('#souteze-verejne', AppFixtures::FREE_GLOBAL_COMPETITION_NAME);
    }

    public function testHeroCarriesNoPrizePoolCard(): void
    {
        $client = static::createClient();
        $client->request('GET', '/souteze');

        self::assertResponseIsSuccessful();
        // The product owner removed „VÝHERNÍ BANK": entry fees are burned credits,
        // there are no payouts, so nothing on this page may read as a prize pool.
        self::assertSelectorTextNotContains('body', 'bank');
        self::assertSelectorTextContains('body', 'Aktivní soutěže');
        self::assertSelectorTextContains('body', 'Hráčů celkem');
        self::assertSelectorTextContains('body', 'Sledovaných zápasů');
    }

    private function login(KernelBrowser $client, string $userId): void
    {
        /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $entityManager->find(User::class, Uuid::fromString($userId));
        self::assertNotNull($user);
        $client->loginUser($user);
    }
}
