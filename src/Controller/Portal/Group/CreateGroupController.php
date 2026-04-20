<?php

declare(strict_types=1);

namespace App\Controller\Portal\Group;

use App\Command\CreateGroup\CreateGroupCommand;
use App\Entity\Group;
use App\Entity\Tournament;
use App\Entity\User;
use App\Form\GroupFormData;
use App\Form\GroupFormType;
use App\Repository\TournamentRepository;
use App\Voter\TournamentVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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

        $autoAllowed = $this->isGranted(TournamentVoter::CREATE_GROUP, $tournament);
        $pinGate = !$autoAllowed && $user->isVerified && $tournament->isActive && $tournament->hasCreationPin;

        if (!$autoAllowed && !$pinGate) {
            throw $this->createAccessDeniedException('Skupinu v tomto turnaji může založit jen vlastník nebo někdo s PINem.');
        }

        $formData = new GroupFormData();
        $form = $this->createForm(GroupFormType::class, $formData, [
            'require_tournament_creation_pin' => $pinGate,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($pinGate && !$this->pinMatches($tournament, $formData->tournamentCreationPin)) {
                $form->get('tournamentCreationPin')->addError(new FormError('PIN turnaje nesouhlasí.'));
            } else {
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
        }

        return $this->render('portal/group/create.html.twig', [
            'form' => $form,
            'tournament' => $tournament,
            'pinGate' => $pinGate,
        ]);
    }

    private function pinMatches(Tournament $tournament, ?string $submitted): bool
    {
        if (null === $tournament->creationPin || null === $submitted) {
            return false;
        }

        return hash_equals($tournament->creationPin, $submitted);
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
