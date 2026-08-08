<?php

declare(strict_types=1);

namespace App\Command\ChangeUserPassword;

use App\Exception\InvalidCurrentPassword;
use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler]
final readonly class ChangeUserPasswordHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ChangeUserPasswordCommand $command): void
    {
        $user = $this->userRepository->get($command->userId);

        // An account with no password at all (a pre-provisioned invitation seat) cannot
        // prove anything here — isPasswordValid() answers false for it, and such a user
        // is sent through „Zapomenuté heslo" exactly like InvitationForm does.
        if (!$this->passwordHasher->isPasswordValid($user, $command->currentPassword)) {
            throw InvalidCurrentPassword::create();
        }

        $hashed = $this->passwordHasher->hashPassword($user, $command->newPassword);
        $user->changePassword($hashed, \DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
