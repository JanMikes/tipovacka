<?php

declare(strict_types=1);

namespace App\Controller\Invitation;

use App\Entity\User;
use App\Enum\InvitationKind;
use App\Exception\InvalidPin;
use App\Form\JoinByPinFormData;
use App\Form\JoinByPinFormType;
use App\Service\Invitation\InvitationAcceptanceService;
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
 * The 8-box PIN bar (`_partials/join_by_pin_form.html.twig`) submits here.
 *
 * Public since B15, for the same reason as the page it belongs to: the bar sits on the
 * homepage and on `/souteze`, both of which anonymous visitors see. Anonymous, a valid
 * PIN is *remembered*, never joined — the visitor is sent to `/pripojit`, which names the
 * competition and offers sign-up or sign-in. A rejected PIN is answered on that page too.
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
            // Practically always a page left open past the session — nothing the visitor typed is wrong.
            $this->addFlash('error', 'Stránka byla otevřená příliš dlouho a z bezpečnostních důvodů vypršela. Zadejte prosím PIN znovu.');

            return $this->redirect($redirectTo);
        }

        // The bar is a strip on a busy page (homepage, Nástěnka, /souteze): a flash about
        // the PIN rendered at the top of that page, a screen away from the boxes, read as
        // „nothing happened". A rejected PIN is answered on the „Znám PIN" page instead —
        // the same form, the typed PIN kept, the reason right under the field (422).
        $form = $this->createForm(JoinByPinFormType::class, new JoinByPinFormData(), [
            'action' => $this->generateUrl('competition_join_by_pin'),
        ]);
        $form->submit(['pin' => (string) $request->request->get('pin', '')]);

        if (!$form->isValid()) {
            return $this->renderRejected($form);
        }

        /** @var JoinByPinFormData $data */
        $data = $form->getData();

        try {
            $context = $this->contextResolver->resolve(
                InvitationKind::Pin,
                $data->pin,
                \DateTimeImmutable::createFromInterface($this->clock->now()),
            );
        } catch (InvalidPin) {
            $form->get('pin')->addError(new FormError(JoinByPinController::UNKNOWN_PIN));

            return $this->renderRejected($form);
        }

        // Expired, revoked, the soutěž already over — the landing names the reason and
        // the way forward, exactly as the „Znám PIN" page does.
        if (InvitationContextStatus::Active !== $context->status) {
            return $this->acceptanceService->renderStatus($context);
        }

        $user = $this->getUser();

        if ($user instanceof User) {
            return $this->acceptanceService->handleAuthenticated($context, $user);
        }

        $this->acceptanceService->rememberIntent($context);

        return $this->redirectToRoute('competition_join_by_pin');
    }

    /**
     * @param FormInterface<JoinByPinFormData> $form
     */
    private function renderRejected(FormInterface $form): Response
    {
        return $this->render('invitation/join_by_pin.html.twig', [
            'form' => $form,
        ]);
    }

    private function safeRedirectTarget(string $candidate): string
    {
        if ('' === $candidate || !str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return $this->generateUrl($this->getUser() instanceof User ? 'dashboard' : 'app_home');
        }

        return $candidate;
    }
}
