<?php

declare(strict_types=1);

namespace App\Command\RegenerateShareableLink;

use App\Repository\GroupRepository;
use App\Service\Group\ShareableLinkTokenGenerator;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegenerateShareableLinkHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private ShareableLinkTokenGenerator $tokenGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegenerateShareableLinkCommand $command): void
    {
        $group = $this->groupRepository->get($command->groupId);
        $group->setShareableLinkToken(
            $this->tokenGenerator->generate(),
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }
}
