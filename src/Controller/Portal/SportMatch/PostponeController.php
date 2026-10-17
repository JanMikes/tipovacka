<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Command\PostponeSportMatch\PostponeSportMatchCommand;
use App\Entity\User;
use App\Repository\MatchEventRepository;
use App\Repository\SportMatchRepository;
use App\Voter\SportMatchVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route(
    '/zapasy/{id}/odlozit',
    name: 'sport_match_postpone',
    requirements: ['id' => Requirement::UUID],
    methods: ['POST'],
)]
#[IsGranted('ROLE_USER')]
final class PostponeController extends AbstractController
{
    public function __construct(
        private readonly SportMatchRepository $sportMatchRepository,
        private readonly MatchEventRepository $matchEventRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $sportMatch = $this->sportMatchRepository->get(Uuid::fromString($id));
        $this->denyAccessUnlessGranted(SportMatchVoter::EDIT, $sportMatch);

        if (!$this->isCsrfTokenValid('sport_match_postpone_'.$sportMatch->id->toRfc4122(), (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Formulář mezitím vypršel, termín se nezměnil. Zadejte ho prosím znovu.');

            return $this->redirectToRoute('sport_match_detail', ['id' => $sportMatch->id->toRfc4122()]);
        }

        $raw = trim((string) $request->request->get('new_kickoff_at', ''));
        $newKickoffAt = $this->parseKickoff($raw);

        if (null === $newKickoffAt) {
            // Re-render the match page with the problem under the field (and what
            // was typed still in it) — a flash at the top of the page sits a
            // screen away from the field on a phone.
            return $this->render('portal/sport_match/detail.html.twig', [
                'sport_match' => $sportMatch,
                'match_events' => $this->matchEventRepository->listByMatch($sportMatch->id),
                'postpone_error' => '' === $raw
                    ? 'Zadejte nový termín zápasu (datum a čas, český čas).'
                    : sprintf('Termín „%s" neumíme přečíst. Vyberte ho v kalendáři, nebo ho napište ve tvaru RRRR-MM-DD HH:MM, např. 2026-05-10 18:00.', $raw),
                'postpone_value' => $raw,
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        try {
            $this->commandBus->dispatch(new PostponeSportMatchCommand(
                sportMatchId: $sportMatch->id,
                editorId: $user->id,
                newKickoffAt: $newKickoffAt,
            ));
        } catch (HandlerFailedException $e) {
            // E.g. the match finished or was cancelled in another tab meanwhile.
            if (!$e->getPrevious() instanceof \DomainException) {
                throw $e;
            }

            $this->addFlash('error', $e->getPrevious()->getMessage());

            return $this->redirectToRoute('sport_match_detail', ['id' => $sportMatch->id->toRfc4122()]);
        }

        $this->addFlash('success', 'Zápas byl odložen.');

        return $this->redirectToRoute('sport_match_detail', ['id' => $sportMatch->id->toRfc4122()]);
    }

    private function parseKickoff(string $raw): ?\DateTimeImmutable
    {
        if ('' === $raw) {
            return null;
        }

        // The submitted value is Czech local time; store it in UTC.
        $localTimezone = new \DateTimeZone('Europe/Prague');

        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!'.$format, $raw, $localTimezone);
            $warnings = \DateTimeImmutable::getLastErrors();

            // createFromFormat() rolls 2026-02-31 over to March — refuse it instead.
            if ($parsed instanceof \DateTimeImmutable && (false === $warnings || 0 === $warnings['warning_count'])) {
                return $parsed->setTimezone(new \DateTimeZone('UTC'));
            }
        }

        return null;
    }
}
