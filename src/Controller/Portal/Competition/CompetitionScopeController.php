<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Repository\CompetitionRepository;
use App\Voter\CompetitionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * „Zápasy soutěže" — the organizer's basket of zdroje, editable at any time.
 * A thin host for {@see \App\Twig\Components\Competition\ScopeEditor}, which is
 * the create wizard's step 1 form; everything the page does happens in there.
 *
 * Private competitions only: a global competition is an admin product whose
 * scope is fixed in the admin area, so the page redirects one there rather than
 * offering an editor whose save would be refused.
 */
#[Route(
    '/souteze/{id}/zapasy',
    name: 'competition_scope',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET'],
)]
#[IsGranted('ROLE_USER')]
final class CompetitionScopeController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::EDIT, $competition);

        if ($competition->isGlobal) {
            $this->addFlash('info', 'Rozsah zápasů globální soutěže se upravuje v administraci.');

            return $this->redirectToRoute('competition_settings', ['id' => $competition->id->toRfc4122()]);
        }

        return $this->render('portal/competition/scope.html.twig', [
            'competition' => $competition,
        ]);
    }
}
