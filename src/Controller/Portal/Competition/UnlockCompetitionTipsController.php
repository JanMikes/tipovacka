<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\UnlockCompetitionTips\UnlockCompetitionTipsCommand;
use App\Entity\User;
use App\Exception\CompetitionTipsCannotBeUnlocked;
use App\Repository\CompetitionRepository;
use App\Voter\CompetitionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{id}/odemknout-tipy',
    name: 'portal_competition_unlock_tips',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class UnlockCompetitionTipsController extends AbstractController
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
        $this->denyAccessUnlessGranted(CompetitionVoter::EDIT, $competition);

        if (!$this->isCsrfTokenValid('competition_unlock_tips_'.$competition->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        try {
            $this->commandBus->dispatch(new UnlockCompetitionTipsCommand(
                editorId: $user->id,
                competitionId: $competition->id,
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();

            // Race: the first match kicked off between rendering the button and
            // this POST. Surface the domain message as a flash and return to the
            // detail page (mirrors the sibling lock/unlock controllers) instead
            // of a 409 error page.
            if ($previous instanceof CompetitionTipsCannotBeUnlocked) {
                $this->addFlash('error', $previous->getMessage());

                return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
            }

            throw $e;
        }

        $this->addFlash('success', 'Tipy byly odemčeny.');

        return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
    }
}
