<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RequireVerifiedEmailSubscriber implements EventSubscriberInterface
{
    /**
     * Path prefixes that require a verified email. Must mirror the authenticated
     * areas in security access_control (see config/packages/security.php).
     */
    private const GATED_PATH_PREFIXES = [
        '/nastenka',
        '/portal',
        '/pripojit',
        '/admin',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User || $user->isVerified) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        foreach (self::GATED_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $event->setResponse(
                    new RedirectResponse($this->urlGenerator->generate('app_verify_email_pending'))
                );

                return;
            }
        }
    }
}
