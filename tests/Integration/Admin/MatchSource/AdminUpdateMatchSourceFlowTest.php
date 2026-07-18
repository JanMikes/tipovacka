<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin\MatchSource;

use App\DataFixtures\AppFixtures;
use App\Entity\MatchSource;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class AdminUpdateMatchSourceFlowTest extends WebTestCase
{
    public function testAdminCanUpdatePublicMatchSource(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $client->request('GET', '/admin/turnaje/'.AppFixtures::PUBLIC_SOURCE_ID.'/upravit');
        self::assertResponseIsSuccessful();

        $client->submitForm('Uložit změny', [
            'match_source_form[name]' => 'Upravený turnaj',
        ]);

        self::assertResponseRedirects('/admin/turnaje');

        $em->clear();
        /** @var MatchSource $matchSource */
        $matchSource = $em->find(MatchSource::class, Uuid::fromString(AppFixtures::PUBLIC_SOURCE_ID));
        self::assertSame('Upravený turnaj', $matchSource->name);
    }

    public function testNonAdminForbidden(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/admin/turnaje/'.AppFixtures::PUBLIC_SOURCE_ID.'/upravit');
        self::assertResponseStatusCodeSame(403);
    }
}
