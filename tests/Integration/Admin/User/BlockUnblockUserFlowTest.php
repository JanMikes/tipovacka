<?php

declare(strict_types=1);

namespace App\Tests\Integration\Admin\User;

use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class BlockUnblockUserFlowTest extends WebTestCase
{
    public function testAdminCanBlockAndUnblockUser(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $admin = $em->find(User::class, Uuid::fromString(AppFixtures::ADMIN_ID));
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $targetId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);

        $client->request('GET', '/admin/uzivatele?search=user%40&verified=all&active=all');
        self::assertResponseIsSuccessful();
        $client->submitForm('Zablokovat');

        self::assertResponseRedirects('/admin/uzivatele');

        $em->clear();
        /** @var User $after */
        $after = $em->find(User::class, $targetId);
        self::assertFalse($after->isActive);

        $client->request('GET', '/admin/uzivatele?search=user%40&verified=all&active=all');
        self::assertResponseIsSuccessful();
        $client->submitForm('Odblokovat');

        self::assertResponseRedirects('/admin/uzivatele');

        $em->clear();
        /** @var User $afterUnblock */
        $afterUnblock = $em->find(User::class, $targetId);
        self::assertTrue($afterUnblock->isActive);
    }

    public function testNonAdminCannotBlock(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = $client->getContainer()->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $client->loginUser($user);

        $targetId = Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID);
        $client->request('POST', '/admin/uzivatele/'.$targetId->toRfc4122().'/zablokovat');

        self::assertResponseStatusCodeSame(403);
    }
}
