<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Entity\Competition;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Enum\CompetitionMatchSelectionMode;
use App\Repository\CompetitionTeamFilterRepository;
use App\Repository\GuessRepository;
use App\Repository\MatchEventRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Competition\TipStatsProvider;
use App\Service\EffectiveTipDeadlineResolver;
use App\Voter\SportMatchVoter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/zapasy/{id}',
    name: 'sport_match_detail',
    requirements: ['id' => Requirement::UUID],
)]
#[IsGranted('ROLE_USER')]
final class SportMatchDetailController extends AbstractController
{
    public function __construct(
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly GuessRepository $guessRepository,
        private readonly MatchEventRepository $matchEventRepository,
        private readonly CompetitionMatchProvider $matchProvider,
        private readonly TipStatsProvider $tipStatsProvider,
        private readonly CompetitionTeamFilterRepository $teamFilterRepository,
        private readonly EffectiveTipDeadlineResolver $deadlineResolver,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(SportMatchVoter::VIEW, $sportMatch);

        $user = $this->getUser();
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $myCompetitionsForMatchSource = [];
        $excludedCompetitions = [];

        if ($user instanceof User) {
            $including = [];

            foreach ($this->membershipRepository->findMyActive($user->id) as $membership) {
                $competition = $membership->competition;

                if ($this->matchProvider->includes($competition, $sportMatch)) {
                    $including[] = $competition;

                    continue;
                }

                // B4: a competition over ANOTHER source has obviously nothing to
                // do with this match. A competition over THIS source, though,
                // raises the „I am a member — why is it not listed?" question,
                // and the user cannot answer it from this page. Collect those
                // and say why the match falls outside their scope.
                if ($competition->matchSource->id->equals($sportMatch->matchSource->id)) {
                    $excludedCompetitions[] = [
                        'id' => $competition->id,
                        'name' => $competition->name,
                        'reason' => $this->exclusionReason($competition, $sportMatch),
                        // Bounded by the viewer's own Teams-mode competitions over
                        // this one source — typically zero or one, so no N+1 risk.
                        'teams' => CompetitionMatchSelectionMode::Teams === $competition->selectionMode
                            ? $this->teamFilterRepository->teamViewsFor($competition->id)
                            : [],
                    ];
                }
            }

            // One batch for every competition on the page — the distribution bar
            // (or its paywall) is per competition, so a per-card resolve would N+1.
            $tipStats = $this->tipStatsProvider->forPairs(
                array_map(static fn ($competition) => [$competition, [$sportMatch]], $including),
                $user,
            );

            foreach ($including as $competition) {
                $guess = $this->guessRepository->findActiveByUserMatchCompetition(
                    $user->id,
                    $sportMatch->id,
                    $competition->id,
                );
                $myCompetitionsForMatchSource[] = [
                    'id' => $competition->id,
                    'name' => $competition->name,
                    'hasGuess' => null !== $guess,
                    // B5: the card must SHOW the locked state — an unfilled tip in a
                    // locked competition is „Netipováno", not a call to action.
                    'isLocked' => $this->deadlineResolver->isLocked($competition, $sportMatch, $user, $now),
                    'deadline' => $this->deadlineResolver->deadlineFor($competition, $sportMatch, $user),
                    'stats' => $tipStats[$this->tipStatsProvider->key($competition->id, $sportMatch->id)] ?? null,
                ];
            }
        }

        return $this->render('portal/sport_match/detail.html.twig', [
            'sport_match' => $sportMatch,
            'my_competitions_for_match_source' => $myCompetitionsForMatchSource,
            'excluded_competitions_for_match_source' => $excludedCompetitions,
            'match_events' => $this->matchEventRepository->listByMatch($sportMatch->id),
        ]);
    }

    /**
     * Why {@see CompetitionMatchProvider::includes} said no, for a competition
     * that lives on the very source this match belongs to. The cases mirror the
     * provider's own branches one-to-one — the deleted-match and foreign-source
     * ones cannot occur here (the page 404s on the former, the caller filters
     * the latter), hence the `other` catch-all rather than a partial match.
     */
    private function exclusionReason(Competition $competition, SportMatch $sportMatch): string
    {
        return match (true) {
            CompetitionMatchSelectionMode::Subset === $competition->selectionMode => 'subset',
            CompetitionMatchSelectionMode::Teams === $competition->selectionMode => 'teams',
            $sportMatch->isPlayoff && !$competition->includePlayoff => 'playoff',
            default => 'other',
        };
    }
}
