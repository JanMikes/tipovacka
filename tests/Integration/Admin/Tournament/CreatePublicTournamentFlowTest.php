<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin\Tournament;

use App\DataFixtures\AppFixtures;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CreatePublicTournamentFlowTest extends WebTestCase
{
    public function testNonAdminCannotAccessCreatePage(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/admin/turnaje/vytvorit');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanCreatePublicTournament(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/turnaje/vytvorit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Nový veřejný turnaj');

        $client->submitForm('Vytvořit turnaj', [
            'tournament_form[name]' => 'Nová liga',
        ]);

        self::assertResponseRedirects();

        $em->clear();
        $tournament = $em->createQueryBuilder()
            ->select('t')
            ->from(Tournament::class, 't')
            ->where('t.name = :name')
            ->setParameter('name', 'Nová liga')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Tournament::class, $tournament);
        self::assertSame(TournamentVisibility::Public, $tournament->visibility);
        self::assertSame(AppFixtures::ADMIN_ID, $tournament->owner->id->toRfc4122());
    }
}
