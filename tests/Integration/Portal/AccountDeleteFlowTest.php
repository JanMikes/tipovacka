<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class AccountDeleteFlowTest extends WebTestCase
{
    public function testConfirmationPageRenders(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/portal/ucet/smazat');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Opravdu chcete smazat');
    }

    public function testPostSoftDeletesAndLogsOut(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        // GET first to ensure session + CSRF token renders
        $crawler = $client->request('GET', '/portal/ucet/smazat');
        self::assertResponseIsSuccessful();

        $client->submitForm('Ano, smazat můj účet');

        self::assertResponseRedirects('/prihlaseni');

        $em->clear();
        $deletedUser = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($deletedUser);
        self::assertTrue($deletedUser->isDeleted());
    }

    public function testDeletedUserCannotLoginAfterDeletion(): void
    {
        $client = static::createClient();

        $client->request('POST', '/prihlaseni', [
            '_username' => AppFixtures::DELETED_USER_EMAIL,
            '_password' => AppFixtures::DEFAULT_PASSWORD,
        ]);

        self::assertResponseRedirects('/prihlaseni');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'smazán');
    }
}
