<?php

declare(strict_types=1);

namespace App\Command\LockCompetitionTips;

use App\Repository\CompetitionRepository;
use App\Service\EffectiveTipDeadlineResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class LockCompetitionTipsHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(LockCompetitionTipsCommand $command): void
    {
        $competition = $this->competitionRepository->get($command->competitionId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        if (null === $command->lockAt) {
            $competition->lockTips($now);

            return;
        }

        // „V určený čas" — the future moment IS the lock; validated against the
        // competition start, so a schedule can never push tips past it.
        $competition->scheduleTipsLock(
            lockAt: $command->lockAt,
            now: $now,
            firstKickoffAt: $this->deadlineResolver->firstKickoffFor($competition),
        );
    }
}
