<?php

declare(strict_types=1);

namespace App\Command\BlockUser;

use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BlockUserHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(BlockUserCommand $command): void
    {
        $user = $this->userRepository->get($command->userId);
        $user->deactivate(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
