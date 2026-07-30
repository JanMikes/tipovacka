<?php

declare(strict_types=1);

namespace App\Command\ForgetPendingJoin;

use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ForgetPendingJoinHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ForgetPendingJoinCommand $command): void
    {
        $user = $this->userRepository->get($command->userId);

        $user->forgetPendingJoin(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
