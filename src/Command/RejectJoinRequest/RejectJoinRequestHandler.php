<?php

declare(strict_types=1);

namespace App\Command\RejectJoinRequest;

use App\Repository\GroupJoinRequestRepository;
use App\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RejectJoinRequestHandler
{
    public function __construct(
        private GroupJoinRequestRepository $joinRequestRepository,
        private UserRepository $userRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RejectJoinRequestCommand $command): void
    {
        $request = $this->joinRequestRepository->get($command->requestId);
        $decider = $this->userRepository->get($command->ownerId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $request->reject($decider, $now);
    }
}
