<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\InviteToGlobalCompetition\InviteToGlobalCompetitionCommand;
use App\Command\SendCompetitionInvitation\SendCompetitionInvitationCommand;
use App\Entity\User;
use App\Form\SendInvitationFormData;
use App\Form\SendInvitationFormType;
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

#[Route(
    '/souteze/{id}/pozvanky/odeslat',
    name: 'competition_invitation_send',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
#[IsGranted('ROLE_USER')]
final class SendInvitationController extends AbstractController
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
        // Not an organizer check any more: every member may bring a friend in, exactly as
        // every member may pass on the PIN. See CompetitionVoter::INVITE_MEMBER.
        $this->denyAccessUnlessGranted(CompetitionVoter::INVITE_MEMBER, $competition);

        $formData = new SendInvitationFormData();
        $form = $this->createForm(SendInvitationFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Two different things wear the same button. A private soutěž pre-provisions
            // the seat so the organizer can tip on the invitee's behalf; a global one must
            // not (that seat costs money), so it only mails the public invitation page.
            $this->commandBus->dispatch($competition->isGlobal
                ? new InviteToGlobalCompetitionCommand(
                    inviterId: $user->id,
                    competitionId: $competition->id,
                    email: $formData->email,
                )
                : new SendCompetitionInvitationCommand(
                    inviterId: $user->id,
                    competitionId: $competition->id,
                    email: $formData->email,
                ));

            $this->addFlash('success', 'Pozvánka byla odeslána.');
        } else {
            foreach ($form->getErrors(true) as $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->redirectToRoute(...$this->backTo($request, $competition->id));
    }

    /**
     * Where the form was submitted from. The organizer's copy lives in „Nastavení →
     * Pozvánky" and belongs back there; a player's copy is the modal on the competition
     * page, which they may not even be allowed to leave for — a plain member has no
     * „Nastavení". Anything unrecognised falls back to the settings page, so the parameter
     * can never redirect somewhere of the submitter's choosing.
     *
     * @return array{string, array{id: string}}
     */
    private function backTo(Request $request, Uuid $competitionId): array
    {
        $route = 'detail' === $request->request->get('navrat')
            ? 'competition_detail'
            : 'competition_settings';

        return [$route, ['id' => $competitionId->toRfc4122()]];
    }
}
