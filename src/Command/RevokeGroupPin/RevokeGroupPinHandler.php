<?php

declare(strict_types=1);

namespace App\Command\RevokeGroupPin;

use App\Repository\GroupRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevokeGroupPinHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokeGroupPinCommand $command): void
    {
        $group = $this->groupRepository->get($command->groupId);
        $group->revokePin(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
