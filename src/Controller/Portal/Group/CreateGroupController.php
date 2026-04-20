<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\CreateGroup\CreateGroupCommand;
use App\Entity\Group;
use App\Entity\User;
use App\Form\GroupFormData;
use App\Form\GroupFormType;
use App\Repository\TournamentRepository;
use App\Voter\TournamentVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/portal/turnaje/{tournamentId}/skupiny/novy',
    name: 'portal_group_create',
    requirements: ['tournamentId' => Requirement::UUID],
)]
final class CreateGroupController extends AbstractController
{
    public function __construct(
        private readonly TournamentRepository $tournamentRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $tournamentId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $tournament = $this->tournamentRepository->get(Uuid::fromString($tournamentId));
        $this->denyAccessUnlessGranted(TournamentVoter::VIEW, $tournament);

        $formData = new GroupFormData();
        $form = $this->createForm(GroupFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $envelope = $this->commandBus->dispatch(new CreateGroupCommand(
                ownerId: $user->id,
                tournamentId: $tournament->id,
                name: $formData->name,
                description: $formData->description ?: null,
                withPin: $formData->withPin,
            ));

            $group = $this->extractGroup($envelope);

            $this->addFlash('success', 'Skupina byla vytvořena.');

            return $this->redirectToRoute('portal_group_detail', ['id' => $group->id->toRfc4122()]);
        }

        return $this->render('portal/group/create.html.twig', [
            'form' => $form,
            'tournament' => $tournament,
        ]);
    }

    private function extractGroup(Envelope $envelope): Group
    {
        $stamp = $envelope->last(HandledStamp::class);

        if (null === $stamp) {
            throw new \LogicException('Command was not handled.');
        }

        $result = $stamp->getResult();

        if (!$result instanceof Group) {
            throw new \LogicException('Expected Group to be returned by handler.');
        }

        return $result;
    }
}
