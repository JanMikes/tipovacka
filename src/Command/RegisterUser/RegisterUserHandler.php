<?php

declare(strict_types=1);

namespace App\Command\RegisterUser;

use App\Entity\User;
use App\Exception\NicknameAlreadyTaken;
use App\Exception\UserAlreadyExists;
use App\Repository\UserRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler]
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegisterUserCommand $command): User
    {
        if (null !== $this->userRepository->findByEmail($command->email)) {
            throw UserAlreadyExists::withEmail($command->email);
        }

        if (null !== $this->userRepository->findByNickname($command->nickname)) {
            throw NicknameAlreadyTaken::withNickname($command->nickname);
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $user = new User(
            id: $this->identity->next(),
            email: $command->email,
            password: null,
            nickname: $command->nickname,
            createdAt: $now,
        );

        $hashed = $this->passwordHasher->hashPassword($user, $command->plainPassword);
        $user->changePassword($hashed, $now);

        if ($command->autoVerify) {
            $user->markAsVerified($now);
        }

        $this->userRepository->save($user);

        return $user;
    }
}
