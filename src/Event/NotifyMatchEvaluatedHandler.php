<?php

declare(strict_types=1);

namespace App\Event;

use App\Enum\NotificationType;
use App\Query\GetCompetitionLeaderboard\GetCompetitionLeaderboard;
use App\Query\QueryBus;
use App\Repository\CompetitionRepository;
use App\Repository\GuessEvaluationRepository;
use App\Repository\SportMatchRepository;
use App\Repository\UserRepository;
use App\Service\Notification\Notifier;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * `match_evaluated`: one notification per (user, competition) whose guess for the
 * just-finished match was evaluated — the points they scored on this match plus
 * their current standing (rank from the post-evaluation leaderboard). Dispatched
 * post-commit so the leaderboard already reflects the new evaluations.
 */
#[AsMessageHandler]
final readonly class NotifyMatchEvaluatedHandler
{
    public function __construct(
        private SportMatchRepository $sportMatchRepository,
        private GuessEvaluationRepository $evaluationRepository,
        private CompetitionRepository $competitionRepository,
        private UserRepository $userRepository,
        private QueryBus $queryBus,
        private Notifier $notifier,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(GuessesEvaluatedForMatch $event): void
    {
        $match = $this->sportMatchRepository->find($event->sportMatchId);

        if (null === $match) {
            return;
        }

        $score = sprintf('%d:%d', $match->homeScore ?? 0, $match->awayScore ?? 0);

        // Group the per-user points by competition so each competition's
        // leaderboard is queried exactly once.
        $byCompetition = [];

        foreach ($this->evaluationRepository->pointsForMatchByCompetition($event->sportMatchId) as $row) {
            $byCompetition[$row['competitionId']][$row['userId']] = (int) $row['points'];
        }

        foreach ($byCompetition as $competitionId => $pointsByUser) {
            $competition = $this->competitionRepository->find(Uuid::fromRfc4122($competitionId));

            if (null === $competition) {
                continue;
            }

            $leaderboard = $this->queryBus->handle(new GetCompetitionLeaderboard($competition->id));
            $rankByUser = [];

            foreach ($leaderboard->rows as $leaderboardRow) {
                $rankByUser[$leaderboardRow->userId->toRfc4122()] = $leaderboardRow->rank;
            }

            $url = $this->urlGenerator->generate(
                'portal_competition_leaderboard',
                ['competitionId' => $competition->id->toRfc4122()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            foreach ($pointsByUser as $userId => $points) {
                $user = $this->userRepository->find(Uuid::fromRfc4122($userId));

                if (null === $user) {
                    continue;
                }

                $rank = $rankByUser[$userId] ?? null;
                $standing = null !== $rank ? sprintf(', jste %d. v soutěži %s', $rank, $competition->name) : sprintf(' v soutěži %s', $competition->name);

                $this->notifier->notify(
                    user: $user,
                    type: NotificationType::MatchEvaluated,
                    title: sprintf('Vyhodnoceno: %s %s %s', $match->homeTeam, $score, $match->awayTeam),
                    body: sprintf('%s %s %s: získáváte %d b.%s', $match->homeTeam, $score, $match->awayTeam, $points, $standing),
                    url: $url,
                    competition: $competition,
                    payload: ['points' => $points, 'rank' => $rank, 'sportMatchId' => $event->sportMatchId->toRfc4122()],
                    dedupKey: sprintf('match_evaluated:%s:%s', $event->sportMatchId->toRfc4122(), $competition->id->toRfc4122()),
                );
            }
        }
    }
}
