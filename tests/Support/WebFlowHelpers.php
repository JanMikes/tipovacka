<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Container helpers for WebTestCase flow tests. Kept out of the individual
 * test files because the referenced services exist only in the test container
 * (this file is excluded from PHPStan, same as IntegrationTestCase).
 */
trait WebFlowHelpers
{
    protected function testEntityManager(): EntityManagerInterface
    {
        /* @var EntityManagerInterface */
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function testCommandBus(): MessageBusInterface
    {
        /* @var MessageBusInterface */
        return static::getContainer()->get('test.command.bus');
    }

    protected function paymentGateway(): FakePaymentGateway
    {
        /* @var FakePaymentGateway */
        return static::getContainer()->get(FakePaymentGateway::class);
    }

    protected function loginUserById(KernelBrowser $client, string $userId): User
    {
        $user = $this->testEntityManager()->find(User::class, Uuid::fromString($userId));
        \PHPUnit\Framework\Assert::assertInstanceOf(User::class, $user);
        $client->loginUser($user);

        return $user;
    }
}
