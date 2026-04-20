<?php

declare(strict_types=1);

namespace App\Tests\Integration\Auth;

use App\Command\BlockUser\BlockUserCommand;
use App\Command\UnblockUser\UnblockUserCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class AdminBlockFlowTest extends WebTestCase
{
    public function testBlockedUserCannotLogin(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $user = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($user);
        $user->deactivate(new \DateTimeImmutable('2025-06-15 12:00:00 UTC'));
        $em->flush();

        $client->request('POST', '/prihlaseni', [
            '_username' => AppFixtures::VERIFIED_USER_EMAIL,
            '_password' => AppFixtures::DEFAULT_PASSWORD,
        ]);

        self::assertResponseRedirects('/prihlaseni');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'zablokován');
    }

    public function testAdminCanBlockAndUnblockUser(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var MessageBusInterface $commandBus */
        $commandBus = $container->get('test.command.bus');

        $commandBus->dispatch(new BlockUserCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        $em->clear();
        $blocked = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($blocked);
        self::assertFalse($blocked->isActive);

        $commandBus->dispatch(new UnblockUserCommand(
            userId: Uuid::fromString(AppFixtures::VERIFIED_USER_ID),
        ));

        $em->clear();
        $unblocked = $em->find(User::class, Uuid::fromString(AppFixtures::VERIFIED_USER_ID));
        self::assertNotNull($unblocked);
        self::assertTrue($unblocked->isActive);
    }
}
