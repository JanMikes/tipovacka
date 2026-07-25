<?php

declare(strict_types=1);

namespace App\Controller\Portal\MatchSource;

use App\Entity\Team;
use App\Repository\MatchSourceRepository;
use App\Repository\TeamRepository;
use App\Voter\MatchSourceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

/**
 * Team autocomplete for the match create/edit picker: the resolution scope of one
 * source — the GLOBAL directory for a curated source, the source's LOCAL teams for
 * a private one (?q=…). Returns [{"name", "shortName", "country"}, …]. Purely an
 * affordance: the form still submits a typed team NAME, resolved server-side.
 */
#[Route(
    '/portal/zdroje/{id}/tymy',
    name: 'portal_match_source_teams',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET'],
)]
final class TeamAutocompleteController extends AbstractController
{
    public function __construct(
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly TeamRepository $teamRepository,
    ) {
    }

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $matchSource = $this->matchSourceRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(MatchSourceVoter::CREATE_MATCH, $matchSource);

        $term = trim((string) $request->query->get('q', ''));

        $teams = $matchSource->isCurated
            ? $this->teamRepository->searchGlobalBySport($matchSource->sport->id, $term)
            : $this->teamRepository->searchLocalBySource($matchSource->id, $term);

        return $this->json(array_map(
            static fn (Team $team): array => [
                'name' => $team->name,
                'shortName' => $team->shortName,
                'country' => $team->country,
            ],
            $teams,
        ));
    }
}
