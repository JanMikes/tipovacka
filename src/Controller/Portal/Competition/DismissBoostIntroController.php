<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\MarkBoostIntroSeen\MarkBoostIntroSeenCommand;
use App\Entity\User;
use App\Repository\CompetitionRepository;
use App\Voter\CompetitionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * „Pochopil jsem, již nezobrazovat" — persists the dismissal of the first-visit
 * boost-price modal (item 19). All three dismissals (the ✕, the button, and
 * Esc / backdrop) post here through the same form, which is what makes them
 * equivalent: `boost_intro_controller.js` submits it in the background on the
 * dialog's `close` event, so there is exactly ONE persistence path and no way
 * to close the dialog without it sticking.
 *
 * Answers 204 to a background submit and redirects a plain one, so the form
 * still works if scripting fails after the dialog opened.
 */
#[Route(
    '/souteze/{id}/vylepseni/uvod/skryt',
    name: 'competition_boost_intro_seen',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
#[IsGranted('ROLE_USER')]
final class DismissBoostIntroController extends AbstractController
{
    public function __construct(
        private readonly CompetitionRepository $competitionRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $competition = $this->competitionRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(CompetitionVoter::VIEW, $competition);

        if (!$this->isCsrfTokenValid('boost_intro_seen_'.$competition->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            return new Response(status: Response::HTTP_BAD_REQUEST);
        }

        $this->commandBus->dispatch(new MarkBoostIntroSeenCommand(
            userId: $user->id,
            competitionId: $competition->id,
        ));

        if ($request->isXmlHttpRequest()) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        return $this->redirectToRoute('competition_detail', ['id' => $competition->id->toRfc4122()]);
    }
}
