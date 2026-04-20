<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\BlockUser\BlockUserCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class BlockUserHandlerTest extends IntegrationTestCase
{
    public function testDeactivatesUser(): void
    {
        $userId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);

        $this->commandBus()->dispatch(new BlockUserCommand(userId: $userId));

        $em = $this->entityManager();
        $em->clear();

        $user = $em->find(User::class, $userId);
        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isActive);
    }
}
