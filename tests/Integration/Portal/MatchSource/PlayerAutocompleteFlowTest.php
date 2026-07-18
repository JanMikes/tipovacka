<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\MatchSource;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class PlayerAutocompleteFlowTest extends WebTestCase
{
    public function testReturnsTeamRosterAsJson(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/portal/zdroje/'.AppFixtures::PUBLIC_SOURCE_ID.'/hraci', [
            'tym' => 'Bohemians 1905',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame([
            ['name' => AppFixtures::PLAYER_HOME_SCORER_ONE_NAME],
            ['name' => AppFixtures::PLAYER_HOME_SCORER_TWO_NAME],
        ], $payload);
    }

    public function testSearchAcrossSourceWithoutTeamFilter(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/portal/zdroje/'.AppFixtures::PUBLIC_SOURCE_ID.'/hraci', [
            'q' => 'dole',
        ]);

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame([['name' => AppFixtures::PLAYER_AWAY_BOOKED_NAME]], $payload);
    }

    public function testOutsiderGetsForbiddenForPrivateSource(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $outsider = $em->find(User::class, Uuid::fromString(AppFixtures::SECOND_VERIFIED_USER_ID));
        self::assertNotNull($outsider);
        $client->loginUser($outsider);

        $client->request('GET', '/portal/zdroje/'.AppFixtures::PRIVATE_SOURCE_ID.'/hraci', [
            'tym' => 'Tygři',
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
