<?php

declare(strict_types=1);

namespace App\Event;

use App\Repository\CompetitionRepository;
use App\Repository\SportMatchRepository;
use App\Service\EffectiveTipDeadlineResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Pins the automatic lock moment when a postponed match was the one defining
 * it. Postponing the competition's earliest included match to a later date
 * would otherwise move the live first-kickoff forward and reopen tips that had
 * already closed — so we freeze `Competition::$tipsLockedAt` at the reached
 * moment (see {@see EffectiveTipDeadlineResolver::lockMomentToPinAfterDefiningMatchLeft}).
 */
#[AsMessageHandler]
final readonly class SportMatchPostponedHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private CompetitionRepository $competitionRepository,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SportMatchPostponed $event): void
    {
        $match = $this->sportMatchRepository->get($event->sportMatchId);
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($this->competitionRepository->findByMatchSource($match->matchSource->id) as $competition) {
            $pinAt = $this->deadlineResolver->lockMomentToPinAfterDefiningMatchLeft(
                $competition,
                $match,
                $event->previousKickoffAt,
                $now,
            );

            if (null !== $pinAt) {
                $competition->pinTipsLockMoment($pinAt, $now);
            }

            // Drop the resolver's per-request cache regardless — the match's
            // kickoff changed under it this request.
            $this->deadlineResolver->forgetCompetition($competition->id);
        }
    }
}
