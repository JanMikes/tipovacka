<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Tournament;

use App\DataFixtures\AppFixtures;
use App\Entity\Tournament;
use App\Entity\User;
use App\Enum\TournamentVisibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class CreatePrivateTournamentFlowTest extends WebTestCase
{
    public function testUnauthenticatedRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/portal/turnaje/vytvorit');

        self::assertResponseRedirects('/prihlaseni');
    }

    public function testVerifiedUserCanLoadCreateForm(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/portal/turnaje/vytvorit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Nový soukromý turnaj');
    }

    public function testVerifiedUserCanCreatePrivateTournament(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/portal/turnaje/vytvorit');
        $client->submitForm('Vytvořit turnaj', [
            'tournament_form[name]' => 'Můj nový turnaj',
        ]);

        self::assertResponseRedirects();

        $em->clear();

        $tournament = $em->createQueryBuilder()
            ->select('t')
            ->from(Tournament::class, 't')
            ->where('t.name = :name')
            ->setParameter('name', 'Můj nový turnaj')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(Tournament::class, $tournament);
        self::assertSame(TournamentVisibility::Private, $tournament->visibility);
        self::assertSame(AppFixtures::VERIFIED_USER_ID, $tournament->owner->id->toRfc4122());
    }
}
