<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\UpdateUserProfile\UpdateUserProfileCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Uid\Uuid;

final class UpdateUserProfileHandlerTest extends IntegrationTestCase
{
    public function testUpdatesProfile(): void
    {
        $userId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);

        $this->commandBus()->dispatch(new UpdateUserProfileCommand(
            userId: $userId,
            firstName: 'Jan',
            lastName: 'Novák',
            phone: '+420123456789',
        ));

        $em = $this->entityManager();
        $em->clear();

        $user = $em->find(User::class, $userId);
        self::assertInstanceOf(User::class, $user);
        self::assertSame('Jan', $user->firstName);
        self::assertSame('Novák', $user->lastName);
        self::assertSame('+420123456789', $user->phone);
    }
}
