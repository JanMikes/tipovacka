<?php

declare(strict_types=1);

namespace App\Command\ResetUserPassword;

use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler]
final readonly class ResetUserPasswordHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ResetUserPasswordCommand $command): void
    {
        $user = $this->userRepository->get($command->userId);
        $hashed = $this->passwordHasher->hashPassword($user, $command->plainPassword);
        $user->changePassword($hashed, \DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
