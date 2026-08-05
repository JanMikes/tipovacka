<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Command\CreateSportMatch\CreateSportMatchCommand;
use App\Entity\SportMatch;
use App\Entity\User;
use App\Form\SportMatchFormData;
use App\Form\SportMatchFormType;
use App\Repository\MatchSourceRepository;
use App\Service\Competition\ScopeReturn;
use App\Voter\SportMatchVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/turnaje/{matchSourceId}/zapasy/novy',
    name: 'sport_match_create',
    requirements: ['matchSourceId' => Requirement::UUID],
)]
#[IsGranted('ROLE_USER')]
final class CreateSportMatchController extends AbstractController
{
    public function __construct(
        private readonly MatchSourceRepository $matchSourceRepository,
        private readonly ScopeReturn $scopeReturn,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $matchSourceId): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $matchSource = $this->matchSourceRepository->get(Uuid::fromString($matchSourceId));
        $this->denyAccessUnlessGranted(SportMatchVoter::CREATE, $matchSource);

        // „Přišel jsem sem ze soutěže" survives the POST through the form action.
        $scopeCompetitionId = $this->scopeReturn->competitionId($request);

        $formData = new SportMatchFormData();
        $form = $this->createForm(SportMatchFormType::class, $formData, [
            'teams_url' => $this->generateUrl('match_source_teams', ['id' => $matchSource->id->toRfc4122()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            \assert($formData->kickoffAt instanceof \DateTimeImmutable);

            $envelope = $this->commandBus->dispatch(new CreateSportMatchCommand(
                matchSourceId: $matchSource->id,
                editorId: $user->id,
                homeTeam: $formData->homeTeam,
                awayTeam: $formData->awayTeam,
                kickoffAt: $formData->kickoffAt,
                venue: $formData->venue ?: null,
                round: $formData->round ?: null,
                isPlayoff: $formData->isPlayoff,
            ));

            $sportMatch = $this->extractSportMatch($envelope);

            $this->addFlash('success', 'Zápas byl vytvořen.');

            // Coming from „Zápasy soutěže", the organizer is typing a whole rozpis
            // in — send them back to it rather than to the new match's page.
            if (null !== $scopeCompetitionId) {
                return $this->redirectToRoute('competition_scope', ['id' => $scopeCompetitionId]);
            }

            return $this->redirectToRoute('sport_match_detail', ['id' => $sportMatch->id->toRfc4122()]);
        }

        return $this->render('portal/sport_match/form.html.twig', [
            'form' => $form,
            'match_source' => $matchSource,
            'mode' => 'create',
            'scope_competition_id' => $scopeCompetitionId,
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
