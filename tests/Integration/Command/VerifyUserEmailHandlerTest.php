<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\VerifyUserEmail\VerifyUserEmailCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Exception\UserAlreadyVerified;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Uid\Uuid;

final class VerifyUserEmailHandlerTest extends IntegrationTestCase
{
    public function testMarksUnverifiedUserAsVerified(): void
    {
        $userId = Uuid::fromString(AppFixtures::UNVERIFIED_USER_ID);

        $this->commandBus()->dispatch(new VerifyUserEmailCommand($userId));

        $em = $this->entityManager();
        $em->clear();

        $user = $em->find(User::class, $userId);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isVerified);
    }

    public function testThrowsWhenAlreadyVerified(): void
    {
        $userId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);

        try {
            $this->commandBus()->dispatch(new VerifyUserEmailCommand($userId));
            self::fail('Expected HandlerFailedException');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(UserAlreadyVerified::class, $e->getPrevious());
        }
    }
}
