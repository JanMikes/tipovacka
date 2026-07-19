<?php

declare(strict_types=1);

namespace App\Command\UnlockCompetitionTips;

use App\Repository\CompetitionRepository;
use App\Service\EffectiveTipDeadlineResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UnlockCompetitionTipsHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UnlockCompetitionTipsCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);

        // Unlock invariant: once the first included match kicked off, the
        // competition is genuinely started and the lock is irreversible.
        $competition->unlockTips(
            now: \DateTimeImmutable::createFromInterface($this->clock->now()),
            firstKickoffAt: $this->deadlineResolver->firstKickoffFor($competition),
        );
    }
}
