<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\SendBulkGroupInvitations\BulkInvitationResult;
use App\Command\SendBulkGroupInvitations\SendBulkGroupInvitationsCommand;
use App\Entity\User;
use App\Form\BulkInvitationFormData;
use App\Form\BulkInvitationFormType;
use App\Repository\GroupRepository;
use App\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/skupiny/{id}/pozvanky/hromadne',
    name: 'portal_group_invitation_send_bulk',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
final class SendBulkInvitationsController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $group = $this->groupRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(GroupVoter::MANAGE_MEMBERS, $group);

        $formData = new BulkInvitationFormData();
        $form = $this->createForm(BulkInvitationFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $envelope = $this->commandBus->dispatch(new SendBulkGroupInvitationsCommand(
                inviterId: $user->id,
                groupId: $group->id,
                rawEmails: $formData->emails,
            ));

            $result = $envelope->last(HandledStamp::class)?->getResult();

            if ($result instanceof BulkInvitationResult) {
                $this->flashSummary($result);
            }

            return $this->redirectToRoute('portal_group_detail', ['id' => $group->id->toRfc4122()]);
        }

        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }

        return $this->redirectToRoute('portal_group_detail', ['id' => $group->id->toRfc4122()]);
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

        if (count($result->invalid) > 0) {
            $pairs = array_map(
                static fn (array $row): string => sprintf('%s (%s)', $row['email'], $row['reason']),
                $result->invalid,
            );
            $this->addFlash('error', sprintf('Neplatné: %s.', implode(', ', $pairs)));
        }

        if (0 === count($result->invited)
            && 0 === count($result->alreadyMembers)
            && 0 === count($result->alreadyPending)
            && 0 === count($result->invalid)
        ) {
            $this->addFlash('info', 'Nebyly rozpoznány žádné e-maily.');
        }
    }
}
