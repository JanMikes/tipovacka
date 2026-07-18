<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

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
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/souteze/{id}/pozvanky/odeslat',
    name: 'portal_competition_invitation_send',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
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
        $this->denyAccessUnlessGranted(CompetitionVoter::INVITE_MEMBER, $competition);

        $formData = new SendInvitationFormData();
        $form = $this->createForm(SendInvitationFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new SendCompetitionInvitationCommand(
                inviterId: $user->id,
                competitionId: $competition->id,
                email: $formData->email,
            ));

            $this->addFlash('success', 'Pozvánka byla odeslána.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        }

        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
    }
}
