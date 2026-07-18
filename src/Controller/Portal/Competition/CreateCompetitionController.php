<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\CreateCompetition\CreateCompetitionCommand;
use App\Entity\Competition;
use App\Entity\MatchSource;
use App\Entity\User;
use App\Form\CompetitionFormData;
use App\Form\CompetitionFormType;
use App\Repository\MatchSourceRepository;
use App\Voter\MatchSourceVoter;
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
    '/portal/turnaje/{matchSourceId}/souteze/novy',
    name: 'portal_competition_create',
    requirements: ['matchSourceId' => Requirement::UUID],
)]
final class CreateCompetitionController extends AbstractController
{
    public function __construct(
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $matchSourceId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $matchSource = $this->matchSourceRepository->get(Uuid::fromString($matchSourceId));
        $this->denyAccessUnlessGranted(MatchSourceVoter::VIEW, $matchSource);

        $autoAllowed = $this->isGranted(MatchSourceVoter::CREATE_COMPETITION, $matchSource);
        $pinGate = !$autoAllowed && $user->isVerified && $matchSource->isActive && $matchSource->hasCreationPin;

        if (!$autoAllowed && !$pinGate) {
            throw $this->createAccessDeniedException('Soutěž v tomto turnaji může založit jen vlastník nebo někdo s PINem.');
        }

        $formData = new CompetitionFormData();
        $form = $this->createForm(CompetitionFormType::class, $formData, [
            'require_match_source_creation_pin' => $pinGate,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($pinGate && !$this->pinMatches($matchSource, $formData->matchSourceCreationPin)) {
                $form->get('matchSourceCreationPin')->addError(new FormError('PIN turnaje nesouhlasí.'));
            } else {
                $envelope = $this->commandBus->dispatch(new CreateCompetitionCommand(
                    ownerId: $user->id,
                    matchSourceId: $matchSource->id,
                    name: $formData->name,
                    description: $formData->description ?: null,
                    withPin: $formData->withPin,
                ));

                $competition = $this->extractCompetition($envelope);

                $this->addFlash('success', 'Soutěž byla vytvořena.');

                return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
            }
        }

        return $this->render('portal/competition/create.html.twig', [
            'form' => $form,
            'match_source' => $matchSource,
            'pinGate' => $pinGate,
        ]);
    }

    private function pinMatches(MatchSource $matchSource, ?string $submitted): bool
    {
        if (null === $matchSource->creationPin || null === $submitted) {
            return false;
        }

        return hash_equals($matchSource->creationPin, $submitted);
    }

    private function extractCompetition(Envelope $envelope): Competition
    {
        $stamp = $envelope->last(HandledStamp::class);

        if (null === $stamp) {
            throw new \LogicException('Command was not handled.');
        }

        $result = $stamp->getResult();

        if (!$result instanceof Competition) {
            throw new \LogicException('Expected Competition to be returned by handler.');
        }

        return $result;
    }
}
