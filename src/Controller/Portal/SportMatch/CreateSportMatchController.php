<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Command\CreateSportMatch\CreateSportMatchCommand;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Form\SportMatchFormData;
use App\Form\SportMatchFormType;
use App\Repository\TournamentRepository;
use App\Voter\SportMatchVoter;
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
    '/portal/turnaje/{tournamentId}/zapasy/novy',
    name: 'portal_sport_match_create',
    requirements: ['tournamentId' => Requirement::UUID],
)]
final class CreateSportMatchController extends AbstractController
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
        $this->denyAccessUnlessGranted(SportMatchVoter::CREATE, $tournament);

        $formData = new SportMatchFormData();
        $form = $this->createForm(SportMatchFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \assert($formData->kickoffAt instanceof \DateTimeImmutable);

            $envelope = $this->commandBus->dispatch(new CreateSportMatchCommand(
                tournamentId: $tournament->id,
                editorId: $user->id,
                homeTeam: $formData->homeTeam,
                awayTeam: $formData->awayTeam,
                kickoffAt: $formData->kickoffAt,
                venue: $formData->venue ?: null,
            ));

            $sportMatch = $this->extractSportMatch($envelope);

            $this->addFlash('success', 'Zápas byl vytvořen.');

            return $this->redirectToRoute('portal_sport_match_detail', ['id' => $sportMatch->id->toRfc4122()]);
        }

        return $this->render('portal/sport_match/form.html.twig', [
            'form' => $form,
            'tournament' => $tournament,
            'mode' => 'create',
        ]);
    }

    private function extractSportMatch(Envelope $envelope): SportMatch
    {
        $stamp = $envelope->last(HandledStamp::class);

        if (null === $stamp) {
            throw new \LogicException('Command was not handled.');
        }

        $result = $stamp->getResult();

        if (!$result instanceof SportMatch) {
            throw new \LogicException('Expected SportMatch to be returned by handler.');
        }

        return $result;
    }
}
