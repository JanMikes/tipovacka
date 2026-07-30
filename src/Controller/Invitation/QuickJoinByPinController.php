<?php

declare(strict_types=1);

namespace App\Controller\Invitation;

use App\Entity\User;
use App\Enum\InvitationKind;
use App\Exception\InvalidPin;
use App\Service\Invitation\InvitationAcceptanceService;
use App\Service\Invitation\InvitationContextResolver;
use App\Service\Invitation\InvitationContextStatus;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The 8-box PIN bar (`_partials/join_by_pin_form.html.twig`) submits here.
 *
 * Public since B15, for the same reason as the page it belongs to: the bar sits on the
 * homepage and on `/souteze`, both of which anonymous visitors see. Anonymous, a valid
 * PIN is *remembered*, never joined — the visitor is sent to `/pripojit`, which names the
 * competition and offers sign-up or sign-in.
 */
#[Route('/pripojit/rychle', name: 'competition_join_by_pin_quick', methods: ['POST'])]
final class QuickJoinByPinController extends AbstractController
{
    public function __construct(
        private readonly InvitationContextResolver $contextResolver,
        private readonly InvitationAcceptanceService $acceptanceService,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
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
            $context = $this->contextResolver->resolve(
                InvitationKind::Pin,
                $pin,
                \DateTimeImmutable::createFromInterface($this->clock->now()),
            );
        } catch (InvalidPin) {
            $this->addFlash('error', 'Zadaný PIN neexistuje.');

            return $this->redirect($redirectTo);
        }

        if (InvitationContextStatus::Active !== $context->status) {
            $this->addFlash('error', 'Zdroj zápasů této soutěže je již ukončen.');

            return $this->redirect($redirectTo);
        }

        $user = $this->getUser();

        if ($user instanceof User) {
            return $this->acceptanceService->handleAuthenticated($context, $user);
        }

        $this->acceptanceService->rememberIntent($context);

        return $this->redirectToRoute('competition_join_by_pin');
    }

    private function safeRedirectTarget(string $candidate): string
    {
        if ('' === $candidate || !str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return $this->generateUrl($this->getUser() instanceof User ? 'dashboard' : 'app_home');
        }

        return $candidate;
    }
}
