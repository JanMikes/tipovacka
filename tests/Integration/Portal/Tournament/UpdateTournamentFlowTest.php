<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Tournament;

use App\DataFixtures\AppFixtures;
use App\Entity\Tournament;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateTournamentFlowTest extends WebTestCase
{
    public function testOwnerCanUpdateTournament(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('GET', '/portal/turnaje/'.AppFixtures::PRIVATE_TOURNAMENT_ID.'/upravit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Uložit změny', [
            'tournament_form[name]' => 'Upravený turnaj',
        ]);

        self::assertResponseRedirects('/portal/turnaje/'.AppFixtures::PRIVATE_TOURNAMENT_ID);

        $em->clear();
        $tournament = $em->find(Tournament::class, Uuid::fromString(AppFixtures::PRIVATE_TOURNAMENT_ID));
        self::assertInstanceOf(Tournament::class, $tournament);
        self::assertSame('Upravený turnaj', $tournament->name);
    }
}
