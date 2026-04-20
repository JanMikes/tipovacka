<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\BlockUser\BlockUserCommand;
use App\Command\UnblockUser\UnblockUserCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class UnblockUserHandlerTest extends IntegrationTestCase
{
    public function testActivatesUser(): void
    {
        $userId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);

        // First block the user, then unblock
        $this->commandBus()->dispatch(new BlockUserCommand(userId: $userId));
        $this->commandBus()->dispatch(new UnblockUserCommand(userId: $userId));

        $em = $this->entityManager();
        $em->clear();

        $user = $em->find(User::class, $userId);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isActive);
    }
}
