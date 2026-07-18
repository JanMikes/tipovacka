<?php

declare(strict_types=1);

namespace App\Controller\Portal\MatchSource;

use App\Entity\Player;
use App\Repository\MatchSourceRepository;
use App\Repository\PlayerRepository;
use App\Voter\MatchSourceVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

/**
 * Scorer-name autocomplete for the score-entry form: the roster pool of one
 * source, optionally narrowed to a team (?tym=…). Returns [{"name": …}, …].
 */
#[Route(
    '/portal/zdroje/{id}/hraci',
    name: 'portal_match_source_players',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET'],
)]
final class PlayerAutocompleteController extends AbstractController
{
    public function __construct(
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly PlayerRepository $playerRepository,
    ) {
    }

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $matchSource = $this->matchSourceRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(MatchSourceVoter::VIEW, $matchSource);

        $teamName = trim((string) $request->query->get('tym', ''));

        $players = '' !== $teamName
            ? $this->playerRepository->listBySourceAndTeam($matchSource->id, $teamName)
            : $this->playerRepository->searchBySource($matchSource->id, trim((string) $request->query->get('q', '')));

        return $this->json(array_map(
            static fn (Player $player): array => ['name' => $player->name],
            $players,
        ));
    }
}
