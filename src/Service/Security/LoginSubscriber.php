<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Command\AcceptCompetitionInvitation\AcceptCompetitionInvitationCommand;
use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\Entity\CompetitionInvitation;
use App\Entity\User;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedMatchSource;
use App\Exception\CompetitionInvitationAlreadyAccepted;
use App\Exception\CompetitionInvitationAlreadyRevoked;
use App\Exception\CompetitionInvitationExpired;
use App\Exception\InvalidInvitationToken;
use App\Exception\InvalidShareableLink;
use App\Service\Competition\CompetitionJoinIntentSession;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly CompetitionJoinIntentSession $joinIntent,
        private readonly InvitationIntentSession $invitationIntent,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $flashBag = (null !== $request && $request->hasSession()) ? $request->getSession()->getFlashBag() : null;

        if (!$user->isVerified) {
            $currentRoute = $request?->attributes->get('_route');

            // During registration, the controller already shows a richer success flash
            // ("Registrace proběhla úspěšně. Zkontrolujte…"), so the generic warning
            // would duplicate it. Only add the warning when the user is re-logging in
            // to an unverified account.
            if ('app_register' !== $currentRoute) {
                $flashBag?->add(
                    'warning',
                    'Nejprve ověřte svou e-mailovou adresu. Zkontrolujte svoji e-mailovou schránku.'
                );
            }

            $event->setResponse(
                new RedirectResponse($this->urlGenerator->generate('app_verify_email_pending'))
            );

            return;
        }

        // Invitation intent takes priority over shareable-link intent.
        $pendingInvitationToken = $this->invitationIntent->consume();

        if (null !== $pendingInvitationToken) {
            $this->handleInvitationIntent($event, $user, $pendingInvitationToken, $flashBag);

            return;
        }

        $pendingLinkToken = $this->joinIntent->consume();

        if (null === $pendingLinkToken) {
            return;
        }

        try {
            $this->commandBus->dispatch(new JoinCompetitionByLinkCommand(
                userId: $user->id,
                token: $pendingLinkToken,
            ));

            $flashBag?->add('success', 'Byl(a) jsi přidán(a) do soutěže.');
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if (
                !($inner instanceof InvalidShareableLink)
                && !($inner instanceof AlreadyMember)
                && !($inner instanceof CannotJoinFinishedMatchSource)
            ) {
                throw $handlerFailed;
            }

            if ($inner instanceof AlreadyMember) {
                $flashBag?->add('info', 'V soutěži již jsi.');
            } else {
                $flashBag?->add('warning', 'Pozvánku do soutěže se nepodařilo uplatnit.');
            }
        } catch (InvalidShareableLink|AlreadyMember|CannotJoinFinishedMatchSource $e) {
            if ($e instanceof AlreadyMember) {
                $flashBag?->add('info', 'V soutěži již jsi.');
            } else {
                $flashBag?->add('warning', 'Pozvánku do soutěže se nepodařilo uplatnit.');
            }
        }

        $event->setResponse(
            new RedirectResponse($this->urlGenerator->generate('portal_dashboard'))
        );
    }

    private function handleInvitationIntent(
        LoginSuccessEvent $event,
        User $user,
        string $token,
        ?\Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface $flashBag,
    ): void {
        try {
            $envelope = $this->commandBus->dispatch(new AcceptCompetitionInvitationCommand(
                userId: $user->id,
                token: $token,
            ));

            $stamp = $envelope->last(HandledStamp::class);
            $invitation = null !== $stamp ? $stamp->getResult() : null;

            if ($invitation instanceof CompetitionInvitation) {
                $flashBag?->add('success', 'Byl(a) jsi přidán(a) do soutěže přes pozvánku.');
                $event->setResponse(new RedirectResponse(
                    $this->urlGenerator->generate('portal_competition_detail', ['id' => $invitation->competition->id->toRfc4122()])
                ));

                return;
            }
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if (
                !($inner instanceof InvalidInvitationToken)
                && !($inner instanceof CompetitionInvitationExpired)
                && !($inner instanceof CompetitionInvitationAlreadyAccepted)
                && !($inner instanceof CompetitionInvitationAlreadyRevoked)
                && !($inner instanceof AlreadyMember)
            ) {
                throw $handlerFailed;
            }

            if ($inner instanceof AlreadyMember) {
                $flashBag?->add('info', 'V soutěži již jsi.');
            } else {
                $flashBag?->add('warning', 'Pozvánku se nepodařilo uplatnit.');
            }
        } catch (InvalidInvitationToken|CompetitionInvitationExpired|CompetitionInvitationAlreadyAccepted|CompetitionInvitationAlreadyRevoked|AlreadyMember $e) {
            if ($e instanceof AlreadyMember) {
                $flashBag?->add('info', 'V soutěži již jsi.');
            } else {
                $flashBag?->add('warning', 'Pozvánku se nepodařilo uplatnit.');
            }
        }

        $event->setResponse(
            new RedirectResponse($this->urlGenerator->generate('portal_dashboard'))
        );
    }
}
