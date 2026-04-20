<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\ResetUserPassword\ResetUserPasswordCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class ResetUserPasswordHandlerTest extends IntegrationTestCase
{
    public function testHashesAndSetsNewPassword(): void
    {
        $userId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);

        $em = $this->entityManager();
        $beforeUser = $em->find(User::class, $userId);
        self::assertInstanceOf(User::class, $beforeUser);
        $oldPassword = $beforeUser->getPassword();

        $this->commandBus()->dispatch(new ResetUserPasswordCommand(
            userId: $userId,
            plainPassword: 'newpassword123',
        ));

        $em->clear();

        $user = $em->find(User::class, $userId);
        self::assertInstanceOf(User::class, $user);
        $newPassword = $user->getPassword();

        self::assertNotNull($newPassword);
        self::assertNotSame($oldPassword, $newPassword);
        // Not stored as plaintext
        self::assertNotSame('newpassword123', $newPassword);
    }
}
