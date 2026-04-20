<?php

declare(strict_types=1);

namespace App\Command\RegenerateGroupPin;

use App\Repository\GroupRepository;
use App\Service\Group\PinGenerator;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegenerateGroupPinHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private PinGenerator $pinGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegenerateGroupPinCommand $command): void
    {
        $group = $this->groupRepository->get($command->groupId);
        $group->setPin(
            $this->pinGenerator->generate(),
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }
}
