<?php

declare(strict_types=1);

namespace App\Command\UpdateUserProfile;

use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateUserProfileHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateUserProfileCommand $command): void
    {
        $user = $this->userRepository->get($command->userId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $user->updateProfile(
            firstName: $command->firstName ?: null,
            lastName: $command->lastName ?: null,
            phone: $command->phone ?: null,
            now: $now,
        );
    }
}
