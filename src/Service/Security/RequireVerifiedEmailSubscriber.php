<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Confines a logged-in but unverified account to the verification airlock.
 *
 * The rule is an ALLOW-list, not a deny-list: anything that is not explicitly public
 * (or part of the verification/escape flow) bounces to `/overeni-ceka`. A deny-list of
 * gated path prefixes silently leaks every time a new authenticated area is added — the
 * previous implementation had exactly that hole for `/zapasy` and for the Live Component
 * endpoint `/_components/…`, through which tips, competitions and preferences are written.
 *
 * Being method-agnostic, it covers POST/write actions as well as GET pages.
 */
final class RequireVerifiedEmailSubscriber implements EventSubscriberInterface
{
    /**
     * Routes an authenticated-but-unverified user may still reach.
     *
     * Deliberately explicit: adding a new public/marketing page means adding it here,
     * whereas forgetting to list a new *portal* page fails closed.
     */
    private const ALLOWED_ROUTES = [
        // The airlock and the verification flow itself.
        'app_verify_email_pending',
        'app_verify_email',
        'app_resend_verification_email',
        // Escape hatches — never lock someone out of leaving or of deleting the account.
        'app_login',
        'app_logout',
        'app_register',
        'account_delete',
        // Password reset: an unverified account must still be recoverable.
        'app_forgot_password_request',
        'app_check_email',
        'app_reset_password',
        'app_reset_password_form',
        // Invitation landings handle the unverified case themselves (see
        // InvitationAcceptanceService::handleAuthenticated): an e-mail invitation proves
        // mailbox ownership and verifies the account on accept, a shareable link stores
        // the intent and sends the user here on its own.
        'competition_accept_invitation',
        'competition_join_by_link',
        // Public / marketing surface.
        'app_home',
        'app_faq',
        'app_features',
        'app_pricing',
        'app_privacy',
        'app_for_business',
        'public_competitions_list',
        'public_match_sources_list_legacy',
        'app_design_styleguide',
        'health_liveness',
        'stripe_webhook',
    ];

    /**
     * Live Components reachable from the allowed pages above — all anonymous-facing auth
     * forms. Every other component (tip submission, the create-competition wizard,
     * notification preferences, …) is a write surface behind the portal and is gated
     * exactly like the page that hosts it.
     */
    private const ALLOWED_LIVE_COMPONENTS = [
        'Auth:RegistrationForm',
        'Auth:InvitationForm',
        'Auth:RequestPasswordResetForm',
        'Auth:ResetPasswordForm',
    ];

    /** Framework-internal endpoints (profiler, web debug toolbar, error preview, fragments). */
    private const ALLOWED_PATH_PREFIXES = [
        '/_wdt',
        '/_profiler',
        '/_error',
        '/_fragment',
    ];

    private const FLASH_MESSAGE = 'Nejprve prosím ověřte svou e-mailovou adresu. Klikněte na odkaz v e-mailu, který jsme vám poslali — teprve pak se vám otevře celá aplikace.';

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Priority 7 — strictly BELOW the security firewall listener (8). At priority 8 the
        // subscriber was registered *before* the firewall, so Security::getUser() still read
        // an empty token storage and the guard never fired in a real request.
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 7],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Resolve the allow-list first: public pages must not force session start /
        // authentication just so we can decide we do not care about them.
        if ($this->isAllowed($request)) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof User || $user->isVerified) {
            return;
        }

        $this->flashOnce($request);

        $event->setResponse(
            new RedirectResponse($this->urlGenerator->generate('app_verify_email_pending'))
        );
    }

    private function isAllowed(Request $request): bool
    {
        $path = $request->getPathInfo();

        foreach (self::ALLOWED_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        $route = $request->attributes->get('_route');

        if (!\is_string($route)) {
            return false;
        }

        if (\in_array($route, self::ALLOWED_ROUTES, true)) {
            return true;
        }

        // All Live Components share the single `ux_live_component` route, so the decision
        // has to be made per component name.
        if ('ux_live_component' === $route) {
            $component = $request->attributes->get('_live_component');

            return \is_string($component) && \in_array($component, self::ALLOWED_LIVE_COMPONENTS, true);
        }

        return false;
    }

    /**
     * A blocked page can pull several sub-resources; queueing the same warning per request
     * would stack it. Add it only when it is not already waiting.
     */
    private function flashOnce(Request $request): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $flashBag = $request->getSession()->getFlashBag();

        if (\in_array(self::FLASH_MESSAGE, $flashBag->peek('warning'), true)) {
            return;
        }

        $flashBag->add('warning', self::FLASH_MESSAGE);
    }
}
