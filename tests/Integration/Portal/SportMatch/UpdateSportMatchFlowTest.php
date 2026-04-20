<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\SportMatch;

use App\DataFixtures\AppFixtures;
use App\Entity\SportMatch;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateSportMatchFlowTest extends WebTestCase
{
    public function testAdminCanUpdateMatch(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/portal/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID.'/upravit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Uložit změny', [
            'sport_match_form[homeTeam]' => 'NEW HOME',
        ]);

        self::assertResponseRedirects();

        $em->clear();
        $match = $em->find(SportMatch::class, Uuid::fromString(AppFixtures::MATCH_SCHEDULED_ID));
        self::assertInstanceOf(SportMatch::class, $match);
        self::assertSame('NEW HOME', $match->homeTeam);
    }
}
