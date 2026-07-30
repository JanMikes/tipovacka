<?php

declare(strict_types=1);

namespace App\Command\RememberPendingJoin;

use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RememberPendingJoinHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RememberPendingJoinCommand $command): void
    {
        $user = $this->userRepository->get($command->userId);

        $user->rememberPendingJoin(
            $command->kind,
            $command->token,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }
}
