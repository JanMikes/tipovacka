<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Repository\MatchEventRepository;
use App\Repository\SportMatchRepository;
use App\Voter\SportMatchVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * The SOURCE-side match page (item 22) — **not** the player's.
 *
 * A player never needs this route: everything they came for (their tip, the tip
 * split, the per-match ranking) is scoped to ONE soutěž and lives on
 * `/souteze/{competitionId}/zapasy/{sportMatchId}`
 * ({@see \App\Controller\Portal\Competition\CompetitionMatchDetailController}).
 * What is left here is what belongs to whoever RUNS the zdroj zápasů: the fixture,
 * what happened in it, and „Správa zápasu".
 *
 * Gated by {@see SportMatchVoter::MANAGE} = admin OR the match source's owner. Not
 * `ROLE_ADMIN`: a `private` source belongs to whatever ordinary user created a
 * from-scratch soutěž, and this page is where every management action lands (12
 * redirects), so a literal admin check would 403 that organizer the instant they
 * saved a score.
 */
#[Route(
    '/zapasy/{id}',
    name: 'sport_match_detail',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET'],
)]
#[IsGranted('ROLE_USER')]
final class SportMatchDetailController extends AbstractController
{
    public function __construct(
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MatchEventRepository $matchEventRepository,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(SportMatchVoter::MANAGE, $sportMatch);

        return $this->render('portal/sport_match/detail.html.twig', [
            'sport_match' => $sportMatch,
            'match_events' => $this->matchEventRepository->listByMatch($sportMatch->id),
        ]);
    }
}
