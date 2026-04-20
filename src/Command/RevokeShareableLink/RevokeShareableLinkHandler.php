<?php

declare(strict_types=1);

namespace App\Command\RevokeShareableLink;

use App\Repository\GroupRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevokeShareableLinkHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokeShareableLinkCommand $command): void
    {
        $group = $this->groupRepository->get($command->groupId);
        $group->revokeShareableLinkToken(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
