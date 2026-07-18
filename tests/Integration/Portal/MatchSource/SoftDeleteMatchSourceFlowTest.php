<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\MatchSource;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class SoftDeleteMatchSourceFlowTest extends WebTestCase
{
    public function testOwnerCanSoftDeleteMatchSource(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        $client->request('GET', '/portal/turnaje/'.AppFixtures::PRIVATE_SOURCE_ID);
        self::assertResponseIsSuccessful();

        $client->submitForm('Smazat zdroj zápasů');

        self::assertResponseRedirects('/nastenka');

        $em->clear();
        $matchSource = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertTrue($matchSource->isDeleted());
    }
}
