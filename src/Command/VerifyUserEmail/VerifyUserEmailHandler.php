<?php

declare(strict_types=1);

namespace App\Command\VerifyUserEmail;

use App\Exception\UserAlreadyVerified;
use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class VerifyUserEmailHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(VerifyUserEmailCommand $command): void
    {
        $user = $this->userRepository->get($command->userId);

        if ($user->isVerified) {
            throw UserAlreadyVerified::create();
        }

        $user->markAsVerified(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
