<?php

declare(strict_types=1);

namespace App\Command\SoftDeleteGroup;

use App\Repository\GroupRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SoftDeleteGroupHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SoftDeleteGroupCommand $command): void
    {
        $group = $this->groupRepository->get($command->groupId);
        $group->softDelete(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
