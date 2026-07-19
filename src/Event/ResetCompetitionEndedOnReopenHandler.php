<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\NotificationType;
use App\Repository\CompetitionRepository;
use App\Repository\NotificationRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * When a match source is reopened (more matches to play), any „competition
 * ended" standing already sent is stale. For every competition on the source we
 * clear the {@see \App\Entity\Competition::$endedNotifiedAt} guard AND delete its
 * `competition_ended` notifications (incl. the dedup markers), so a corrected
 * final standing re-sends after the source is re-completed.
 */
#[AsMessageHandler]
final readonly class ResetCompetitionEndedOnReopenHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private NotificationRepository $notificationRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(MatchSourceReopened $event): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($this->competitionRepository->findByMatchSource($event->matchSourceId) as $competition) {
            $competition->clearEndedNotified($now);
            $this->notificationRepository->deleteByCompetitionAndType($competition->id, NotificationType::CompetitionEnded);
        }
    }
}
