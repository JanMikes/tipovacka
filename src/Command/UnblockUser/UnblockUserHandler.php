<?php

declare(strict_types=1);

namespace App\Command\UnblockUser;

use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UnblockUserHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UnblockUserCommand $command): void
    {
        $user = $this->userRepository->get($command->userId);
        $user->activate(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
