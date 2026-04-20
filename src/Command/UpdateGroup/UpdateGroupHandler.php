<?php

declare(strict_types=1);

namespace App\Command\UpdateGroup;

use App\Repository\GroupRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateGroupHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateGroupCommand $command): void
    {
        $group = $this->groupRepository->get($command->groupId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $group->updateDetails(
            name: $command->name,
            description: $command->description,
            now: $now,
        );
    }
}
