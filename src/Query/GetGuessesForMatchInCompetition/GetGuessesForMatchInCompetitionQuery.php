<?php

declare(strict_types=1);

namespace App\Query\GetGuessesForMatchInCompetition;

use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\SportMatchRepository;
use App\Service\EffectiveTipDeadlineResolver;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetGuessesForMatchInCompetitionQuery
{
    public function __construct(
        private GuessRepository $guessRepository,
        private CompetitionRepository $competitionRepository,
        private SportMatchRepository $sportMatchRepository,
        private EffectiveTipDeadlineResolver $deadlineResolver,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetGuessesForMatchInCompetition $query): GuessesForMatchInCompetitionResult
    {
        $guesses = $this->guessRepository->listActiveByCompetitionAndMatch(
            $query->competitionId,
            $query->sportMatchId,
        );

        $hideOthers = false;

        if ($query->applyHiding) {
            $competition = $this->competitionRepository->get($query->competitionId);
            $sportMatch = $this->sportMatchRepository->get($query->sportMatchId);
            $now = \DateTimeImmutable::createFromInterface($this->clock->now());
            // Visibility is competition-wide (no per-viewer entitlement): others'
            // tips stay hidden until the match's generic effective deadline.
            $deadline = $this->deadlineResolver->deadlineFor($competition, $sportMatch);
            $hideOthers = $now < $deadline;
        }

        $items = array_map(
            static function ($g) use ($query, $hideOthers): GuessForMatchItem {
                $isMine = $g->user->id->equals($query->viewerId);
                $hidden = $hideOthers && !$isMine;

                $scorerNames = [];

                if (!$hidden) {
                    foreach ($g->scorers as $scorer) {
                        $scorerNames[] = $scorer->player->name;
                    }

                    sort($scorerNames);
                }

                return new GuessForMatchItem(
                    userId: $g->user->id,
                    nickname: $g->user->displayName,
                    homeScore: $hidden ? null : $g->homeScore,
                    awayScore: $hidden ? null : $g->awayScore,
                    submittedAt: $g->submittedAt,
                    updatedAt: $g->updatedAt,
                    isMine: $isMine,
                    hidden: $hidden,
                    periodScores: $hidden ? null : $g->periodScores?->toArray(),
                    overtimeHomeScore: $hidden ? null : $g->overtimeHomeScore,
                    overtimeAwayScore: $hidden ? null : $g->overtimeAwayScore,
                    scorerNames: $scorerNames,
                );
            },
            $guesses,
        );

        return new GuessesForMatchInCompetitionResult(items: $items);
    }
}
