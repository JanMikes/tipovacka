<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Competition;
use App\Enum\NotificationType;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\CzechPlural;
use App\Service\Notification\Notifier;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * `competition_ended`: each active member gets their final standing (a winner
 * congratulation for rank 1) once a competition is truly over — the source is
 * marked complete AND every included match is finished+evaluated (no match still
 * Scheduled/Live/Postponed, so standings can no longer move).
 *
 * Two entry points funnel through {@see notifyIfEnded}, guarded by
 * {@see Competition::$endedNotifiedAt} + the per-competition dedup key so members
 * get exactly ONE notification:
 *
 *  - {@see onMatchSourceCompleted} covers "the last match was already evaluated
 *    before the source was completed";
 *  - {@see onGuessesEvaluated} covers the race where the source is completed
 *    BEFORE the last match's evaluation commits — after each match evaluation we
 *    re-check whether the (already completed) competition is now fully evaluated.
 */
final readonly class NotifyCompetitionEndedHandler
{
    public function __construct(
        private CompetitionRepository $competitionRepository,
        private MembershipRepository $membershipRepository,
        private SportMatchRepository $sportMatchRepository,
        private CompetitionMatchProvider $matchProvider,
        private QueryBus $queryBus,
        private Notifier $notifier,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[AsMessageHandler]
    public function onMatchSourceCompleted(MatchSourceCompleted $event): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($this->competitionRepository->findByMatchSource($event->matchSourceId) as $competition) {
            $this->notifyIfEnded($competition, $now);
        }
    }

    #[AsMessageHandler]
    public function onGuessesEvaluated(GuessesEvaluatedForMatch $event): void
    {
        $match = $this->sportMatchRepository->find($event->sportMatchId);

        if (null === $match) {
            return;
        }

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());

        foreach ($this->competitionRepository->findByMatchSource($match->matchSource->id) as $competition) {
            $this->notifyIfEnded($competition, $now);
        }
    }

    private function notifyIfEnded(Competition $competition, \DateTimeImmutable $now): void
    {
        // One-shot: never re-notify a competition that already ended.
        if (null !== $competition->endedNotifiedAt) {
            return;
        }

        // The manager must have confirmed the schedule is complete ...
        if (!$competition->matchSource->isCompleted) {
            return;
        }

        // ... and every included match must be finished+evaluated, so the final
        // standing reflects the real points (not a mid-evaluation snapshot).
        if ($this->matchProvider->hasUnsettledMatches($competition)) {
            return;
        }

        $leaderboard = $this->queryBus->handle(new GetCompetitionLeaderboard($competition->id));
        $totalPlayers = count($leaderboard->rows);

        $rowByUser = [];

        foreach ($leaderboard->rows as $row) {
            $rowByUser[$row->userId->toRfc4122()] = $row;
        }

        $url = $this->urlGenerator->generate(
            'portal_competition_leaderboard',
            ['competitionId' => $competition->id->toRfc4122()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        foreach ($this->membershipRepository->findActiveByCompetition($competition->id) as $membership) {
            $row = $rowByUser[$membership->user->id->toRfc4122()] ?? null;

            if (null === $row) {
                continue;
            }

            $isWinner = 1 === $row->rank;
            $players = CzechPlural::hracu($totalPlayers);
            $title = $isWinner
                ? sprintf('Gratulujeme — vyhráli jste soutěž %s!', $competition->name)
                : sprintf('Soutěž %s skončila', $competition->name);
            $body = $isWinner
                ? sprintf('Soutěž %s skončila a vy jste vyhráli! 1. místo z %d %s s %d b. Gratulujeme!', $competition->name, $totalPlayers, $players, $row->totalPoints)
                : sprintf('Soutěž %s skončila — skončili jste na %d. místě z %d %s s %d b.', $competition->name, $row->rank, $totalPlayers, $players, $row->totalPoints);

            $this->notifier->notify(
                user: $membership->user,
                type: NotificationType::CompetitionEnded,
                title: $title,
                body: $body,
                url: $url,
                competition: $competition,
                payload: ['rank' => $row->rank, 'points' => $row->totalPoints, 'totalPlayers' => $totalPlayers],
                dedupKey: sprintf('competition_ended:%s', $competition->id->toRfc4122()),
            );
        }

        $competition->markEndedNotified($now);
    }
}
