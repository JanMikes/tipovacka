<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\MatchSource;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class MarkMatchSourceFinishedFlowTest extends WebTestCase
{
    public function testOwnerCanMarkMatchSourceAsFinished(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $owner = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($owner);
        $client->loginUser($owner);

        // GET detail page to get the form with CSRF token rendered
        $client->request('GET', '/portal/turnaje/'.AppFixtures::PRIVATE_SOURCE_ID);
        self::assertResponseIsSuccessful();

        $client->submitForm('Ukončit turnaj');

        self::assertResponseRedirects('/portal/turnaje/'.AppFixtures::PRIVATE_SOURCE_ID);

        $em->clear();
        $matchSource = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PRIVATE_SOURCE_ID));
        self::assertInstanceOf(MatchSource::class, $matchSource);
        self::assertTrue($matchSource->isFinished);
    }
}
