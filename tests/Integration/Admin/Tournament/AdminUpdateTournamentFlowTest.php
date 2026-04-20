<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin\Tournament;

use App\DataFixtures\AppFixtures;
use App\Entity\Tournament;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class AdminUpdateTournamentFlowTest extends WebTestCase
{
    public function testAdminCanUpdatePublicTournament(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/turnaje/'.AppFixtures::PUBLIC_TOURNAMENT_ID.'/upravit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Uložit změny', [
            'tournament_form[name]' => 'Upravený turnaj',
        ]);

        self::assertResponseRedirects('/admin/turnaje');

        $em->clear();
        /** @var Tournament $tournament */
        $tournament = $em->find(Tournament::class, Uuid::fromString(AppFixtures::PUBLIC_TOURNAMENT_ID));
        self::assertSame('Upravený turnaj', $tournament->name);
    }

    public function testNonAdminForbidden(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/admin/turnaje/'.AppFixtures::PUBLIC_TOURNAMENT_ID.'/upravit');
        self::assertResponseStatusCodeSame(403);
    }
}
