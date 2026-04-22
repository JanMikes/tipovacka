<?php

declare(strict_types=1);

namespace App\Command\SetGroupMatchDeadline;

use App\Entity\GroupMatchSetting;
use App\Exception\GroupMatchDeadlineAfterKickoff;
use App\Repository\GroupMatchSettingRepository;
use App\Repository\GroupRepository;
use App\Repository\SportMatchRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetGroupMatchDeadlineHandler
{
    public function __construct(
        private GroupRepository $groupRepository,
        private SportMatchRepository $sportMatchRepository,
        private GroupMatchSettingRepository $settingRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SetGroupMatchDeadlineCommand $command): void
    {
        $group = $this->groupRepository->get($command->groupId);
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);
        $existing = $this->settingRepository->findByGroupAndMatch($group->id, $sportMatch->id);

        if (null === $command->deadline) {
            if (null !== $existing) {
                $this->settingRepository->remove($existing);
            }

            return;
        }

        if ($command->deadline > $sportMatch->kickoffAt) {
            throw GroupMatchDeadlineAfterKickoff::create();
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        if (null !== $existing) {
            $existing->updateDeadline($command->deadline, $now);

            return;
        }

        $setting = new GroupMatchSetting(
            id: $this->identity->next(),
            group: $group,
            sportMatch: $sportMatch,
            deadline: $command->deadline,
            createdAt: $now,
        );

        $this->settingRepository->save($setting);
    }
}
