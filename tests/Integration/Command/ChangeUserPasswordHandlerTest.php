<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\ChangeUserPassword\ChangeUserPasswordCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Exception\InvalidCurrentPassword;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class ChangeUserPasswordHandlerTest extends IntegrationTestCase
{
    public function testReplacesPasswordWhenCurrentOneMatches(): void
    {
        $userId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);
        $em = $this->entityManager();

        $before = $em->find(User::class, $userId);
        self::assertInstanceOf(User::class, $before);
        $oldHash = $before->getPassword();

        $this->commandBus()->dispatch(new ChangeUserPasswordCommand(
            userId: $userId,
            currentPassword: AppFixtures::DEFAULT_PASSWORD,
            newPassword: 'brandnewpassword123',
        ));

        $em->clear();

        $user = $em->find(User::class, $userId);
        self::assertInstanceOf(User::class, $user);
        self::assertNotSame($oldHash, $user->getPassword());
        self::assertNotSame('brandnewpassword123', $user->getPassword());

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'brandnewpassword123'));
        self::assertFalse($hasher->isPasswordValid($user, AppFixtures::DEFAULT_PASSWORD));
    }

    public function testRejectsWrongCurrentPassword(): void
    {
        $userId = Uuid::fromString(AppFixtures::VERIFIED_USER_ID);
        $em = $this->entityManager();

        $before = $em->find(User::class, $userId);
        self::assertInstanceOf(User::class, $before);
        $oldHash = $before->getPassword();

        try {
            $this->commandBus()->dispatch(new ChangeUserPasswordCommand(
                userId: $userId,
                currentPassword: 'not-the-current-one',
                newPassword: 'brandnewpassword123',
            ));
            self::fail('Expected InvalidCurrentPassword.');
        } catch (HandlerFailedException $e) {
            self::assertInstanceOf(InvalidCurrentPassword::class, $e->getPrevious());
        }

        $em->clear();

        $user = $em->find(User::class, $userId);
        self::assertInstanceOf(User::class, $user);
        self::assertSame($oldHash, $user->getPassword());
    }

    public function testRejectsAccountWithoutPassword(): void
    {
        $userId = Uuid::fromString(AppFixtures::ANONYMOUS_USER_ID);

        $this->expectException(HandlerFailedException::class);

        $this->commandBus()->dispatch(new ChangeUserPasswordCommand(
            userId: $userId,
            currentPassword: '',
            newPassword: 'brandnewpassword123',
        ));
    }
}
