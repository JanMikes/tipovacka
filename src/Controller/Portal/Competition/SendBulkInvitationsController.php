<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\SendBulkCompetitionInvitations\BulkInvitationResult;
use App\Command\SendBulkCompetitionInvitations\SendBulkCompetitionInvitationsCommand;
use App\Entity\User;
use App\Form\BulkInvitationFormData;
use App\Form\BulkInvitationFormType;
use App\Repository\CompetitionRepository;
use App\Voter\CompetitionVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/souteze/{id}/pozvanky/hromadne',
    name: 'competition_invitation_send_bulk',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
#[IsGranted('ROLE_USER')]
final class SendBulkInvitationsController extends AbstractController
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
        // Stays the ORGANIZER's tool even though INVITE_MEMBER opened up to every member:
        // this one pastes a whole address book and pre-provisions a seat for each, which is
        // the setup half of running a competition, not the „bring a friend" half.
        $this->denyAccessUnlessGranted(CompetitionVoter::MANAGE_JOIN_MECHANICS, $competition);

        $formData = new BulkInvitationFormData();
        // Explicit action: a refused form is re-rendered on the settings page.
        $form = $this->createForm(BulkInvitationFormType::class, $formData, [
            'action' => $this->generateUrl('competition_invitation_send_bulk', ['id' => $competition->id->toRfc4122()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $envelope = $this->commandBus->dispatch(new SendBulkCompetitionInvitationsCommand(
                inviterId: $user->id,
                competitionId: $competition->id,
                rawEmails: $formData->emails,
            ));

            $result = $envelope->last(HandledStamp::class)?->getResult();

            if ($result instanceof BulkInvitationResult) {
                $this->flashSummary($result);

                // The good addresses are on their way; the bad ones come back in the
                // textarea, each named under it, so the organizer fixes exactly those
                // lines and sends again — instead of retyping them from a flash.
                if ([] !== $result->invalid) {
                    return $this->rejected($competition->id->toRfc4122(), $this->retryFormFor($result, $competition->id->toRfc4122()));
                }
            }

            return $this->redirectToRoute('competition_settings', ['id' => $competition->id->toRfc4122()]);
        }

        return $this->rejected($competition->id->toRfc4122(), $form);
    }

    /**
     * @param FormInterface<mixed> $form
     */
    private function rejected(string $competitionId, FormInterface $form): Response
    {
        return $this->forward(CompetitionSettingsController::class, [
            'id' => $competitionId,
            'rejectedBulkInvitationForm' => $form,
        ]);
    }

    /**
     * A bulk form holding only the refused addresses, one per line, with the
     * reason under the field. Form CSRF is off app-wide, so re-submitting it here
     * is what makes it render as a rejected form (aria-invalid, error summary).
     *
     * @return FormInterface<mixed>
     */
    private function retryFormFor(BulkInvitationResult $result, string $competitionId): FormInterface
    {
        $emails = array_map(static fn (array $row): string => $row['email'], $result->invalid);

        $retry = $this->createForm(BulkInvitationFormType::class, new BulkInvitationFormData(), [
            'action' => $this->generateUrl('competition_invitation_send_bulk', ['id' => $competitionId]),
        ]);
        $retry->submit(['emails' => implode("\n", $emails)]);
        $retry->get('emails')->addError(new FormError(sprintf(
            1 === count($emails)
                ? 'Adresu %s jsme nepozvali — není to platný e-mail. Opravte ji (zkontrolujte zavináč a doménu) a odešlete znovu.'
                : 'Tyto adresy jsme nepozvali — nejsou to platné e-maily: %s. Opravte je (zkontrolujte zavináč a doménu) a odešlete znovu.',
            implode(', ', $emails),
        )));

        return $retry;
    }

    private function flashSummary(BulkInvitationResult $result): void
    {
        if (count($result->invited) > 0) {
            $this->addFlash('success', sprintf('Odesláno pozvánek: %d.', count($result->invited)));
        }

        if (count($result->alreadyMembers) > 0) {
            $this->addFlash('info', sprintf(
                'Už jsou členy: %s.',
                implode(', ', $result->alreadyMembers),
            ));
        }

        if (count($result->alreadyPending) > 0) {
            $this->addFlash('info', sprintf(
                'Pozvánka už čeká: %s.',
                implode(', ', $result->alreadyPending),
            ));
        }

        if (0 === count($result->invited)
            && 0 === count($result->alreadyMembers)
            && 0 === count($result->alreadyPending)
            && 0 === count($result->invalid)
        ) {
            $this->addFlash('info', 'V textu jsme nenašli žádnou e-mailovou adresu. Vložte adresy oddělené čárkou, mezerou nebo novým řádkem.');
        }
    }
}
