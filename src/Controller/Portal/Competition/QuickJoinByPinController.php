<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use App\Command\JoinCompetitionByPin\JoinCompetitionByPinCommand;
use App\Entity\Competition;
use App\Entity\User;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedMatchSource;
use App\Exception\InvalidPin;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/pripojit/rychle', name: 'portal_competition_join_by_pin_quick', methods: ['POST'])]
final class QuickJoinByPinController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $redirectTo = $this->safeRedirectTarget((string) $request->request->get('redirect_to', ''));

        if (!$this->isCsrfTokenValid('join_by_pin_quick', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkus to znovu.');

            return $this->redirect($redirectTo);
        }

        $pin = trim((string) $request->request->get('pin', ''));

        if (1 !== preg_match('/^\d{8}$/', $pin)) {
            $this->addFlash('error', 'PIN musí mít přesně 8 číslic.');

            return $this->redirect($redirectTo);
        }

        try {
            $envelope = $this->commandBus->dispatch(new JoinCompetitionByPinCommand(
                userId: $user->id,
                pin: $pin,
            ));

            $competition = $this->extractCompetition($envelope);

            $this->addFlash('success', 'Byl(a) jsi přidán(a) do soutěže.');

            return $this->redirectToRoute('portal_competition_detail', ['id' => $competition->id->toRfc4122()]);
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if ($inner instanceof InvalidPin) {
                $this->addFlash('error', 'Zadaný PIN neexistuje.');
            } elseif ($inner instanceof AlreadyMember) {
                $this->addFlash('error', 'V soutěži už jsi.');
            } elseif ($inner instanceof CannotJoinFinishedMatchSource) {
                $this->addFlash('error', 'Zdroj zápasů této soutěže je již ukončen.');
            } else {
                throw $handlerFailed;
            }

            return $this->redirect($redirectTo);
        }
    }

    private function safeRedirectTarget(string $candidate): string
    {
        if ('' === $candidate || !str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return $this->generateUrl('portal_dashboard');
        }

        return $candidate;
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
