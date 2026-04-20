<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\SoftDeleteUser\SoftDeleteUserCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class SoftDeleteUserHandlerTest extends IntegrationTestCase
{
    public function testSoftDeletesUser(): void
    {
        $userId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);

        $this->commandBus()->dispatch(new SoftDeleteUserCommand(userId: $userId));

        $em = $this->entityManager();
        $em->clear();

        $user = $em->find(User::class, $userId);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isDeleted());
        self::assertNotNull($user->deletedAt);
    }
}
