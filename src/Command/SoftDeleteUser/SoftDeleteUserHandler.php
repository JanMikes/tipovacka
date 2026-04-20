<?php

declare(strict_types=1);

namespace App\Command\SoftDeleteUser;

use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SoftDeleteUserHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SoftDeleteUserCommand $command): void
    {
        $user = $this->userRepository->get($command->userId);
        $user->softDelete(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
