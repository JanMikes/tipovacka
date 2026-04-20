<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
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
        }
    }
}
