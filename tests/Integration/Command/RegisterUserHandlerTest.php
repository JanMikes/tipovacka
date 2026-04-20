<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Command\RegisterUser\RegisterUserCommand;
use App\DataFixtures\AppFixtures;
use App\Entity\User;
use App\Tests\Support\IntegrationTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

final class RegisterUserHandlerTest extends IntegrationTestCase
{
    public function testRegistersUserWithCorrectState(): void
    {
        $this->commandBus()->dispatch(new RegisterUserCommand(
            email: 'new@example.test',
            nickname: 'nove_jmeno',
            plainPassword: 'password123',
        ));

        $em = $this->entityManager();
        $em->clear();

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.email = :e')
            ->setParameter('e', 'new@example.test')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(User::class, $user);
        self::assertSame('nove_jmeno', $user->nickname);
        self::assertFalse($user->isVerified);
        self::assertTrue($user->isActive);
        self::assertFalse($user->isDeleted());
        // Hashed password, not raw
        self::assertNotSame('password123', $user->getPassword());
        self::assertNotNull($user->getPassword());
    }

    public function testThrowsOnDuplicateEmail(): void
    {
        $this->expectException(HandlerFailedException::class);

        $this->commandBus()->dispatch(new RegisterUserCommand(
            email: AppFixtures::VERIFIED_USER_EMAIL,
            nickname: 'jine_jmeno',
            plainPassword: 'password123',
        ));
    }

    public function testThrowsOnDuplicateNickname(): void
    {
        $this->expectException(HandlerFailedException::class);

        $this->commandBus()->dispatch(new RegisterUserCommand(
            email: 'brand-new@example.test',
            nickname: AppFixtures::VERIFIED_USER_NICKNAME,
            plainPassword: 'password123',
        ));
    }

    public function testAutoVerifyMarksUserVerifiedOnRegister(): void
    {
        $this->commandBus()->dispatch(new RegisterUserCommand(
            email: 'autoverified@example.test',
            nickname: 'autoverified',
            plainPassword: 'password123',
            autoVerify: true,
        ));

        $em = $this->entityManager();
        $em->clear();

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from(User::class, 'u')
            ->where('u.email = :e')
            ->setParameter('e', 'autoverified@example.test')
            ->getQuery()
            ->getOneOrNullResult();

        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isVerified);
    }
}
