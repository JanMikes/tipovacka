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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Scorer-name autocomplete for the score-entry form: the roster of one team
 * (?team=<uuid>). Returns [{"name": …}, …]. The source in the route scopes the
 * voter check; the team id is server-rendered from the match on that page.
 */
#[Route(
    '/zdroje/{id}/hraci',
    name: 'match_source_players',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET'],
)]
#[IsGranted('ROLE_USER')]
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

        $teamId = trim((string) $request->query->get('team', ''));

        if ('' === $teamId || !Uuid::isValid($teamId)) {
            return $this->json([]);
        }

        $players = $this->playerRepository->listByTeam(Uuid::fromString($teamId));

        return $this->json(array_map(
            static fn (Player $player): array => ['name' => $player->name],
            $players,
        ));
    }
}
