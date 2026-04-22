<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\SetGroupMatchDeadline\SetGroupMatchDeadlineCommand;
use App\Entity\User;
use App\Exception\GroupMatchDeadlineAfterKickoff;
use App\Form\GroupMatchDeadlineFormData;
use App\Form\GroupMatchDeadlineFormType;
use App\Repository\GroupRepository;
use App\Repository\SportMatchRepository;
use App\Voter\GroupVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/skupiny/{groupId}/zapasy/{sportMatchId}/uzaverka',
    name: 'portal_group_sport_match_set_deadline',
    requirements: ['groupId' => Requirement::UUID, 'sportMatchId' => Requirement::UUID],
    methods: ['POST'],
)]
final class SetGroupMatchDeadlineController extends AbstractController
{
    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $groupId, string $sportMatchId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $group = $this->groupRepository->get(Uuid::fromString($groupId));
        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($sportMatchId));

        $this->denyAccessUnlessGranted(GroupVoter::EDIT, $group);

        $formData = new GroupMatchDeadlineFormData();
        $form = $this->createForm(GroupMatchDeadlineFormType::class, $formData);
        $form->handleRequest($request);

        $redirect = $this->redirectToRoute('portal_group_sport_match_guesses', [
            'groupId' => $group->id->toRfc4122(),
            'sportMatchId' => $sportMatch->id->toRfc4122(),
        ]);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Neplatný vstup uzávěrky.');

            return $redirect;
        }

        try {
            $this->commandBus->dispatch(new SetGroupMatchDeadlineCommand(
                editorId: $user->id,
                groupId: $group->id,
                sportMatchId: $sportMatch->id,
                deadline: $formData->deadline,
            ));
        } catch (HandlerFailedException $e) {
            $previous = $e->getPrevious();

            if ($previous instanceof GroupMatchDeadlineAfterKickoff) {
                $this->addFlash('error', $previous->getMessage());

                return $redirect;
            }

            throw $e;
        }

        $this->addFlash(
            'success',
            null === $formData->deadline ? 'Uzávěrka byla zrušena.' : 'Uzávěrka byla uložena.',
        );

        return $redirect;
    }
}
