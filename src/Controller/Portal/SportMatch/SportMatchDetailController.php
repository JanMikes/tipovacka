<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Entity\User;
use App\Repository\GuessRepository;
use App\Repository\MatchEventRepository;
use App\Repository\MembershipRepository;
use App\Repository\SportMatchRepository;
use App\Service\Competition\CompetitionMatchProvider;
use App\Service\Competition\TipStatsProvider;
use App\Voter\SportMatchVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/zapasy/{id}',
    name: 'portal_sport_match_detail',
    requirements: ['id' => Requirement::UUID],
)]
final class SportMatchDetailController extends AbstractController
{
    public function __construct(
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly GuessRepository $guessRepository,
        private readonly MatchEventRepository $matchEventRepository,
        private readonly CompetitionMatchProvider $matchProvider,
        private readonly TipStatsProvider $tipStatsProvider,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(SportMatchVoter::VIEW, $sportMatch);

        $user = $this->getUser();
        $myCompetitionsForMatchSource = [];

        if ($user instanceof User) {
            $including = [];

            foreach ($this->membershipRepository->findMyActive($user->id) as $membership) {
                if ($this->matchProvider->includes($membership->competition, $sportMatch)) {
                    $including[] = $membership->competition;
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
                    'stats' => $tipStats[$this->tipStatsProvider->key($competition->id, $sportMatch->id)] ?? null,
                ];
            }
        }

        return $this->render('portal/sport_match/detail.html.twig', [
            'sport_match' => $sportMatch,
            'my_competitions_for_match_source' => $myCompetitionsForMatchSource,
            'match_events' => $this->matchEventRepository->listByMatch($sportMatch->id),
        ]);
    }
}
