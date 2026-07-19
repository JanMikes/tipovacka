<?php

declare(strict_types=1);

namespace App\Command\UpdatePremiumSettings;

use App\Repository\CompetitionRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdatePremiumSettingsHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdatePremiumSettingsCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        $competition->setPremiumFeatures(
            showDistribution: $command->showDistribution,
            showOthersTips: $command->showOthersTips,
            allowTipChanges: $command->allowTipChanges,
            tipChangeOffsetMinutes: $command->tipChangeOffsetMinutes,
            now: $now,
        );
    }
}
