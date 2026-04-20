<?php

declare(strict_types=1);

namespace App\Tests\Integration\Portal\Guess;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class SportMatchGuessesFlowTest extends WebTestCase
{
    public function testMemberCanLoadGuessesPage(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request(
            'GET',
            '/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID.'/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID,
        );

        self::assertResponseIsSuccessful();
    }

    public function testNonMemberIsDenied(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $verified = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($verified);
        $client->loginUser($verified);

        $client->request(
            'GET',
            '/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID.'/zapasy/'.AppFixtures::MATCH_SCHEDULED_ID,
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testShowsFixtureGuessForFinishedMatch(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request(
            'GET',
            '/portal/skupiny/'.AppFixtures::PUBLIC_GROUP_ID.'/zapasy/'.AppFixtures::MATCH_FINISHED_ID,
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '3 : 0');
    }
}
