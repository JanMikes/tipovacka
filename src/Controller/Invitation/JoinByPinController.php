<?php

declare(strict_types=1);

namespace App\Controller\Invitation;

use App\Entity\User;
use App\Enum\InvitationKind;
use App\Exception\InvalidPin;
use App\Form\JoinByPinFormData;
use App\Form\JoinByPinFormType;
use App\Service\Competition\PendingJoinStore;
use App\Service\Invitation\InvitationAcceptanceService;
use App\Service\Invitation\InvitationContext;
use App\Service\Invitation\InvitationContextResolver;
use App\Service\Invitation\InvitationContextStatus;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * „Znám PIN" — the third way into a competition, and **public** since B15.
 *
 * A PIN is a join secret exactly like a shareable link, so it earns the same landing: an
 * anonymous visitor may type one, is told which competition it opens, and signs up or
 * signs in from there with the join already remembered. Demanding an account first meant
 * the PIN was forgotten by the time one existed.
 *
 * The PIN never travels in the URL — the pending join lives in the session, so this page
 * shows the „you are about to join X" landing on a plain reload and the browser history
 * carries no secret.
 */
#[Route('/pripojit', name: 'competition_join_by_pin')]
final class JoinByPinController extends AbstractController
{
    public function __construct(
        private readonly InvitationContextResolver $contextResolver,
        private readonly InvitationAcceptanceService $acceptanceService,
        private readonly PendingJoinStore $pendingJoins,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $user = $this->getUser();

        $formData = new JoinByPinFormData();
        $form = $this->createForm(JoinByPinFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $context = $this->contextResolver->resolve(InvitationKind::Pin, $formData->pin, $now);
            } catch (InvalidPin) {
                $form->get('pin')->addError(new FormError('Zadaný PIN neexistuje.'));

                return $this->renderForm($form);
            }

            if (InvitationContextStatus::Active !== $context->status) {
                return $this->acceptanceService->renderStatus($context);
            }

            if ($user instanceof User) {
                return $this->acceptanceService->handleAuthenticated($context, $user);
            }

            // No account yet — name the competition and remember the PIN, so signing up
            // or signing in from the landing lands the visitor inside it.
            $this->acceptanceService->rememberIntent($context);

            return $this->renderLanding($context);
        }

        // Arrived from the 8-box PIN bar (or simply reloaded): an anonymous visitor with a
        // remembered PIN should see the competition, not an empty form asking again.
        if (!$user instanceof User) {
            $pending = $this->pendingJoins->peekAnonymous();

            if (null !== $pending && InvitationKind::Pin === $pending->kind) {
                try {
                    $context = $this->contextResolver->resolve(InvitationKind::Pin, $pending->token, $now);
                } catch (InvalidPin) {
                    return $this->renderForm($form);
                }

                return InvitationContextStatus::Active === $context->status
                    ? $this->renderLanding($context)
                    : $this->acceptanceService->renderStatus($context);
            }
        }

        return $this->renderForm($form);
    }

    /**
     * @param FormInterface<JoinByPinFormData> $form
     */
    private function renderForm(FormInterface $form): Response
    {
        return $this->render('invitation/join_by_pin.html.twig', [
            'form' => $form,
        ]);
    }

    private function renderLanding(InvitationContext $context): Response
    {
        return $this->render('invitation/landing.html.twig', [
            'step' => 'form',
            'context' => $context,
        ]);
    }
}
