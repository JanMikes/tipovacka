<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Command\JoinGroupByLink\JoinGroupByLinkCommand;
use App\Entity\User;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedTournament;
use App\Exception\InvalidShareableLink;
use App\Service\Group\GroupJoinIntentSession;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly GroupJoinIntentSession $intent,
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

        if (!$user->isVerified) {
            $request = $this->requestStack->getCurrentRequest();

            if (null !== $request && $request->hasSession()) {
                $request->getSession()->getFlashBag()->add(
                    'warning',
                    'Nejprve ověřte svou e-mailovou adresu. Zkontrolujte svoji e-mailovou schránku.'
                );
            }

            $event->setResponse(
                new RedirectResponse($this->urlGenerator->generate('app_verify_email_pending'))
            );

            return;
        }

        $pendingToken = $this->intent->consume();

        if (null === $pendingToken) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $flashBag = (null !== $request && $request->hasSession()) ? $request->getSession()->getFlashBag() : null;

        try {
            $this->commandBus->dispatch(new JoinGroupByLinkCommand(
                userId: $user->id,
                token: $pendingToken,
            ));

            $flashBag?->add('success', 'Byl(a) jsi přidán(a) do skupiny.');
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if (
                !($inner instanceof InvalidShareableLink)
                && !($inner instanceof AlreadyMember)
                && !($inner instanceof CannotJoinFinishedTournament)
            ) {
                throw $handlerFailed;
            }

            if ($inner instanceof AlreadyMember) {
                $flashBag?->add('info', 'Ve skupině již jsi.');
            } else {
                $flashBag?->add('warning', 'Pozvánku do skupiny se nepodařilo uplatnit.');
            }
        } catch (InvalidShareableLink|AlreadyMember|CannotJoinFinishedTournament $e) {
            if ($e instanceof AlreadyMember) {
                $flashBag?->add('info', 'Ve skupině již jsi.');
            } else {
                $flashBag?->add('warning', 'Pozvánku do skupiny se nepodařilo uplatnit.');
            }
        }

        $event->setResponse(
            new RedirectResponse($this->urlGenerator->generate('portal_dashboard'))
        );
    }
}
