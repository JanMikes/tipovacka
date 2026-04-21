<?php

declare(strict_types=1);

namespace App\Service\Invitation;

use App\Command\AcceptGroupInvitation\AcceptGroupInvitationCommand;
use App\Command\JoinGroupByLink\JoinGroupByLinkCommand;
use App\Entity\User;
use App\Enum\InvitationKind;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedTournament;
use App\Exception\GroupInvitationAlreadyAccepted;
use App\Exception\GroupInvitationAlreadyRevoked;
use App\Exception\GroupInvitationExpired;
use App\Service\Group\GroupJoinIntentSession;
use App\Service\Security\InvitationIntentSession;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Shared post-authentication handling of an invitation context. Both the email and
 * shareable-link controllers, and the unified live component once the user is logged in,
 * funnel through this service to dispatch the right join command and route the response.
 */
final readonly class InvitationAcceptanceService
{
    public function __construct(
        private InvitationContextResolver $contextResolver,
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
        private ClockInterface $clock,
        private InvitationIntentSession $invitationIntent,
        private GroupJoinIntentSession $joinIntent,
        private Environment $twig,
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    /**
     * Routes an authenticated user landing on an invitation URL: verification gate,
     * email-mismatch detection (email-kind only), or immediate join.
     */
    public function handleAuthenticated(InvitationContext $context, User $currentUser): Response
    {
        if (!$currentUser->isVerified) {
            $this->rememberIntent($context);
            $this->flash('warning', 'Nejprve si ověř svou e-mailovou adresu.');

            return new RedirectResponse($this->urlGenerator->generate('app_verify_email_pending'));
        }

        if (InvitationKind::Email === $context->kind
            && null !== $context->presetEmail
            && (null === $currentUser->email || 0 !== strcasecmp($currentUser->email, $context->presetEmail))
        ) {
            return new Response($this->twig->render('invitation/landing.html.twig', [
                'step' => 'email_mismatch',
                'context' => $context,
                'current_user_email' => $currentUser->email,
            ]));
        }

        return $this->joinGroupAsUser($context, $currentUser);
    }

    /**
     * Dispatches the join command appropriate for the kind, mapping known business
     * exceptions into flash + appropriate redirects.
     */
    public function joinGroupAsUser(InvitationContext $context, User $user): Response
    {
        try {
            $command = InvitationKind::Email === $context->kind
                ? new AcceptGroupInvitationCommand(userId: $user->id, token: $context->token)
                : new JoinGroupByLinkCommand(userId: $user->id, token: $context->token);

            $this->commandBus->dispatch($command);

            $this->flash('success', 'Byl(a) jsi přidán(a) do skupiny.');
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if ($inner instanceof AlreadyMember) {
                $this->flash('info', 'Ve skupině již jsi.');
            } elseif ($inner instanceof CannotJoinFinishedTournament) {
                $this->flash('warning', 'Turnaj této skupiny je již ukončen.');

                return new RedirectResponse($this->urlGenerator->generate('portal_dashboard'));
            } elseif ($inner instanceof GroupInvitationExpired
                || $inner instanceof GroupInvitationAlreadyAccepted
                || $inner instanceof GroupInvitationAlreadyRevoked
            ) {
                return $this->renderStatus($this->refreshContext($context));
            } else {
                throw $handlerFailed;
            }
        }

        return new RedirectResponse($this->urlGenerator->generate(
            'portal_group_detail',
            ['id' => $context->groupId->toRfc4122()],
        ));
    }

    public function rememberIntent(InvitationContext $context): void
    {
        match ($context->kind) {
            InvitationKind::Email => $this->invitationIntent->store($context->token),
            InvitationKind::ShareableLink => $this->joinIntent->store($context->token),
        };
    }

    public function flash(string $type, string $message): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->hasSession()) {
            return;
        }

        $request->getSession()->getFlashBag()->add($type, $message);
    }

    public function refreshContext(InvitationContext $context): InvitationContext
    {
        return $this->contextResolver->resolve(
            $context->kind,
            $context->token,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }

    public function renderStatus(InvitationContext $context): Response
    {
        return new Response($this->twig->render('invitation/landing.html.twig', [
            'step' => $context->status->value,
            'context' => $context,
        ]));
    }
}
