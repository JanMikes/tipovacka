<?php

declare(strict_types=1);

namespace App\Command\SetCompetitionMatchDeadline;

use App\Entity\CompetitionMatchSetting;
use App\Exception\CompetitionMatchDeadlineAfterKickoff;
use App\Repository\CompetitionMatchSettingRepository;
use App\Repository\CompetitionRepository;
use App\Repository\SportMatchRepository;
use App\Service\Identity\ProvideIdentity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SetCompetitionMatchDeadlineHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private SportMatchRepository $sportMatchRepository,
        private CompetitionMatchSettingRepository $settingRepository,
        private ProvideIdentity $identity,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SetCompetitionMatchDeadlineCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $sportMatch = $this->sportMatchRepository->get($command->sportMatchId);
        $existing = $this->settingRepository->findByCompetitionAndMatch($competition->id, $sportMatch->id);

        if (null === $command->deadline) {
            if (null !== $existing) {
                $this->settingRepository->remove($existing);
            }

            return;
        }

        if ($command->deadline > $sportMatch->kickoffAt) {
            throw CompetitionMatchDeadlineAfterKickoff::create();
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        if (null !== $existing) {
            $existing->updateDeadline($command->deadline, $now);

            return;
        }

        $setting = new CompetitionMatchSetting(
            id: $this->identity->next(),
            competition: $competition,
            sportMatch: $sportMatch,
            deadline: $command->deadline,
            createdAt: $now,
        );

        $this->settingRepository->save($setting);
    }
}
