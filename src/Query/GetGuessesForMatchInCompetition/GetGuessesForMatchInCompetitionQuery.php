<?php

declare(strict_types=1);

namespace App\Query\GetGuessesForMatchInCompetition;

use App\Repository\CompetitionRepository;
use App\Repository\GuessRepository;
use App\Repository\SportMatchRepository;
use App\Repository\UserRepository;
use App\Service\Competition\TipVisibilityGate;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetGuessesForMatchInCompetitionQuery
{
    public function __construct(
        private GuessRepository $guessRepository,
        private CompetitionRepository $competitionRepository,
        private SportMatchRepository $sportMatchRepository,
        private UserRepository $userRepository,
        private TipVisibilityGate $visibilityGate,
    ) {
    }

    public function __invoke(GetGuessesForMatchInCompetition $query): GuessesForMatchInCompetitionResult
    {
        $guesses = $this->guessRepository->listActiveByCompetitionAndMatch(
            $query->competitionId,
            $query->sportMatchId,
        );

        // Per-viewer visibility: this viewer's entitlement (premium toggle / own
        // boost) OR the match's userless deadline having passed. A viewer with the
        // OthersTips boost sees concrete tips before the deadline; others do not.
        $competition = $this->competitionRepository->get($query->competitionId);
        $sportMatch = $this->sportMatchRepository->get($query->sportMatchId);
        $viewer = $this->userRepository->find($query->viewerId);

        $hideOthers = !$this->visibilityGate->canSeeOthersTips($competition, $viewer, $sportMatch);

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
