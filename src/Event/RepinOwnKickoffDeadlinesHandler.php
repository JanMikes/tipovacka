<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\CompetitionMatchSetting;
use App\Repository\CompetitionMatchSettingRepository;
use App\Repository\SportMatchRepository;
use App\Service\EffectiveTipDeadlineResolver;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Keeps a „tipovat až do výkopu tohoto zápasu" override pinned to the kickoff it
 * was pinned to, when that kickoff moves.
 *
 * The seeded global soutěže pin every match's deadline to its own kickoff
 * (`app:tip-opening:bulk-set --deadline-own-kickoff`), which writes a
 * CompetitionMatchSetting whose `deadline` EQUALS the match's kickoff. Several
 * of those kickoffs are placeholders — Chance Liga rounds 6+ were seeded at
 * 00:00 Prague because LFA had not published the real times. The moment a feed
 * corrects such a kickoff to 15:00, the override still says 00:00 and, being
 * row 1 of {@see EffectiveTipDeadlineResolver}'s decision table, wins: tipping
 * on that match closes fifteen hours early, silently, for a whole round.
 *
 * So an override whose deadline is EXACTLY the old kickoff follows the match.
 * Anything else is a deliberate manager decision („do it an hour before") and is
 * left strictly alone — the equality is what identifies the pin.
 */
final readonly class RepinOwnKickoffDeadlinesHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private CompetitionMatchSettingRepository $settingRepository,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /** Admin edit of a kickoff, and the reschedule of a postponed match. */
    #[AsMessageHandler]
    public function onUpdated(SportMatchUpdated $event): void
    {
        if (null === $event->previousKickoffAt) {
            return;
        }

        $this->repin($event->sportMatchId, $event->previousKickoffAt);
    }

    /**
     * A postponement moves the kickoff too. The match is untippable while
     * postponed, but the override has to be right again the moment it is
     * rescheduled — and a postponement to a KNOWN new date is exactly when the
     * pin matters most.
     */
    #[AsMessageHandler]
    public function onPostponed(SportMatchPostponed $event): void
    {
        $this->repin($event->sportMatchId, $event->previousKickoffAt);
    }

    private function repin(Uuid $sportMatchId, \DateTimeImmutable $previousKickoffAt): void
    {
        $match = $this->sportMatchRepository->find($sportMatchId);

        if (null === $match) {
            return;
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($this->settingRepository->listByMatch($sportMatchId) as $setting) {
            if (!$this->isPinnedToOwnKickoff($setting, $previousKickoffAt)) {
                continue;
            }

            $setting->updateWindow(
                deadline: $match->kickoffAt,
                opensAt: $setting->opensAt,
                openingNote: $setting->openingNote,
                now: $now,
            );

            $this->deadlineResolver->forgetCompetition($setting->competition->id);

            $this->logger->info('Tip deadline followed a moved kickoff.', [
                'sportMatchId' => $sportMatchId->toRfc4122(),
                'competitionId' => $setting->competition->id->toRfc4122(),
                'from' => $previousKickoffAt->format(\DATE_ATOM),
                'to' => $match->kickoffAt->format(\DATE_ATOM),
            ]);
        }
    }

    private function isPinnedToOwnKickoff(CompetitionMatchSetting $setting, \DateTimeImmutable $previousKickoffAt): bool
    {
        return null !== $setting->deadline
            && $setting->deadline->getTimestamp() === $previousKickoffAt->getTimestamp();
    }
}
