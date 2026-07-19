<?php

declare(strict_types=1);

namespace App\Tests\Integration\Public;

use App\Command\MarkMatchSourceCompleted\MarkMatchSourceCompletedCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class PublicCompetitionsListFlowTest extends WebTestCase
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

    public function testNonGlobalCompetitionsAreNotListed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/souteze');

        self::assertResponseIsSuccessful();
        // PUBLIC_COMPETITION ("Admin liga") is not global ⇒ not discoverable.
        self::assertSelectorTextNotContains('body', AppFixtures::PUBLIC_COMPETITION_NAME);
    }

    public function testLegacyTurnajeRedirectsToSouteze(): void
    {
        $client = static::createClient();
        $client->request('GET', '/turnaje');

        self::assertResponseRedirects('/souteze', 301);
    }

    public function testVerifiedNonMemberSeesJoinButton(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/souteze');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action$="/pripojit-se"]');
    }

    public function testInsufficientCreditsShowsTopUpStateInsteadOfJoinButton(): void
    {
        $client = static::createClient();
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        // VERIFIED_USER has no wallet ⇒ 0 credits; the paid global costs 50.
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/souteze');

        self::assertResponseIsSuccessful();
        // Upfront „Máte 0/50 kreditů — dokoupit" state, NOT a bare join button that
        // would bounce to the top-up page.
        self::assertSelectorTextContains('body', 'Máte 0/'.AppFixtures::GLOBAL_COMPETITION_ENTRY_FEE.' kreditů');
        self::assertSelectorNotExists('form[action="/portal/souteze/'.AppFixtures::GLOBAL_COMPETITION_ID.'/pripojit-se"]');
        // The free global still offers a direct join.
        self::assertSelectorExists('form[action="/portal/souteze/'.AppFixtures::FREE_GLOBAL_COMPETITION_ID.'/pripojit-se"]');
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
}
