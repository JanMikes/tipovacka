<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

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
 * Team autocomplete for the competition team filter (create wizard + manage
 * page): the teams that actually play in a source (home or away), name-filtered
 * by ?q=…. Returns [{"id", "name", "shortName", "country"}, …] — keyed on the
 * team UUID because the filter stores team identities, not names.
 */
#[Route(
    '/portal/zdroje/{id}/filtr-tymy',
    name: 'portal_competition_source_filter_teams',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET'],
)]
final class SourceTeamFilterAutocompleteController extends AbstractController
{
    public function __construct(
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly TeamRepository $teamRepository,
    ) {
    }

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $matchSource = $this->matchSourceRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(MatchSourceVoter::CREATE_COMPETITION, $matchSource);

        $term = trim((string) $request->query->get('q', ''));

        return $this->json(array_map(
            static fn (Team $team): array => [
                'id' => $team->id->toRfc4122(),
                'name' => $team->name,
                'shortName' => $team->shortName,
                'country' => $team->country,
            ],
            $this->teamRepository->searchTeamsInSource($matchSource->id, $term),
        ));
    }
}
