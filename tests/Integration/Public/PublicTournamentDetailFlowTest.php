<?php

declare(strict_types=1);

namespace App\Tests\Integration\Public;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class PublicTournamentDetailFlowTest extends WebTestCase
{
    public function testAnonymousSeesDetailAndLoginPrompt(): void
    {
        $client = static::createClient();
        $client->request('GET', '/turnaje/'.AppFixtures::PUBLIC_TOURNAMENT_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', AppFixtures::PUBLIC_TOURNAMENT_NAME);
        self::assertSelectorTextContains('body', AppFixtures::PUBLIC_GROUP_NAME);
        self::assertSelectorTextContains('body', 'Přihlásit se');
    }

    public function testAuthenticatedNonMemberSeesRequestJoinButton(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        // Second verified user has no pending join request, so the button should be visible.
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/turnaje/'.AppFixtures::PUBLIC_TOURNAMENT_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Požádat o připojení');
    }

    public function testUserWithPendingRequestSeesPendingLabel(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        // Verified user has a pending join request for PUBLIC_GROUP in the fixtures.
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/turnaje/'.AppFixtures::PUBLIC_TOURNAMENT_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Žádost čeká na schválení');
        self::assertSelectorTextNotContains('body', 'Požádat o připojení');
    }

    public function testMemberSeesMemberBadge(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/turnaje/'.AppFixtures::PUBLIC_TOURNAMENT_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Jste členem');
        self::assertSelectorTextNotContains('body', 'Požádat o připojení');
    }

    public function testPrivateTournamentReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/turnaje/'.AppFixtures::PRIVATE_TOURNAMENT_ID);

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownTournamentReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/turnaje/019eeeee-0000-7000-8000-000000000aaa');

        self::assertResponseStatusCodeSame(404);
    }
}
