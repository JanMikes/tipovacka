<?php

declare(strict_types=1);

namespace App\Controller\Portal\Tournament;

use App\Command\CreatePrivateTournament\CreatePrivateTournamentCommand;
use App\Entity\Tournament;
use App\Entity\User;
use App\Form\TournamentFormData;
use App\Form\TournamentFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/turnaje/vytvorit', name: 'portal_tournament_create')]
final class CreatePrivateTournamentController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $formData = new TournamentFormData();
        $form = $this->createForm(TournamentFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $envelope = $this->commandBus->dispatch(new CreatePrivateTournamentCommand(
                ownerId: $user->id,
                name: $formData->name,
                description: $formData->description ?: null,
                startAt: $formData->startAt,
                endAt: $formData->endAt,
            ));

            $tournament = $this->extractTournament($envelope);

            $this->addFlash('success', 'Turnaj byl vytvořen.');

            return $this->redirectToRoute('portal_tournament_detail', ['id' => $tournament->id->toRfc4122()]);
        }

        return $this->render('portal/tournament/create_private.html.twig', [
            'form' => $form,
        ]);
    }

    private function extractTournament(Envelope $envelope): Tournament
    {
        $stamp = $envelope->last(HandledStamp::class);

        if (null === $stamp) {
            throw new \LogicException('Command was not handled.');
        }

        $result = $stamp->getResult();

        if (!$result instanceof Tournament) {
            throw new \LogicException('Expected Tournament to be returned by handler.');
        }

        return $result;
    }
}
