<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * The safety net for item 09 („drop the /portal URL prefix").
 *
 * Authentication used to be inferred from the URL (`access_control` on `^/portal`,
 * `^/nastenka`, `^/zapasy`, `^/pripojit`). Item 09 deleted that prefix, so the path can no
 * longer carry the audience boundary — it is now expressed as `#[IsGranted]` on the
 * controller. This test pins the guarantee that survives the move:
 *
 *   every page that required a login before still requires one, and every page that was
 *   public before is still public.
 *
 * It is therefore keyed by **controller class**, not by route name or path — those are
 * exactly the things item 09 changed, whereas the controllers did not move. The expectation
 * map below is the written-down inventory the item asked for; it was recorded against the
 * pre-item behaviour and must keep passing unchanged afterwards.
 *
 * `true`  = an anonymous request bounces to the login page.
 * `false` = an anonymous request is served (any status other than a redirect to /prihlaseni:
 *           200, 404 for a bogus id, 400 for a webhook without a signature, …).
 */
final class AnonymousReachabilityTest extends WebTestCase
{
    private const LOGIN_PATH = '/prihlaseni';

    /**
     * Every controller reachable through the router, and whether an anonymous visitor is
     * sent to the login page. Adding a route without listing its controller here fails the
     * inventory test below — which is the point: new authenticated areas must be a
     * deliberate entry, not an accident.
     *
     * @var array<class-string, bool>
     */
    private const REQUIRES_LOGIN = [
        // --- Admin (^/admin, ROLE_ADMIN) — untouched by item 09 -----------------------
        \App\Controller\Admin\Competition\AdminDeleteCompetitionController::class => true,
        \App\Controller\Admin\Competition\CreateGlobalCompetitionController::class => true,
        \App\Controller\Admin\Competition\EditGlobalCompetitionController::class => true,
        \App\Controller\Admin\Competition\ListCompetitionsController::class => true,
        \App\Controller\Admin\Competition\SponsorCompetitionPremiumController::class => true,
        \App\Controller\Admin\Credits\ListCreditLedgerController::class => true,
        \App\Controller\Admin\Credits\ListCreditPurchasesController::class => true,
        \App\Controller\Admin\MatchSource\AdminDeleteMatchSourceController::class => true,
        \App\Controller\Admin\MatchSource\AdminMarkCompletedController::class => true,
        \App\Controller\Admin\MatchSource\AdminUpdateMatchSourceController::class => true,
        \App\Controller\Admin\MatchSource\CreateCuratedMatchSourceController::class => true,
        \App\Controller\Admin\MatchSource\ListMatchSourcesController::class => true,
        \App\Controller\Admin\Rule\ListRulesController::class => true,
        \App\Controller\Admin\Team\CreateTeamController::class => true,
        \App\Controller\Admin\Team\EditTeamController::class => true,
        \App\Controller\Admin\Team\ListTeamsController::class => true,
        \App\Controller\Admin\User\AdjustCreditsController::class => true,
        \App\Controller\Admin\User\BlockUserController::class => true,
        \App\Controller\Admin\User\ListUsersController::class => true,
        \App\Controller\Admin\User\UnblockUserController::class => true,

        // --- Auth --------------------------------------------------------------------
        \App\Controller\Auth\LoginController::class => false,
        // The firewall's logout listener answers before any controller and always sends an
        // anonymous visitor to the login page. Recorded as observed, not as an access rule.
        \App\Controller\Auth\LogoutController::class => true,
        \App\Controller\Auth\PasswordResetCheckEmailController::class => false,
        \App\Controller\Auth\PasswordResetController::class => false,
        \App\Controller\Auth\PasswordResetRequestController::class => false,
        \App\Controller\Auth\RegistrationController::class => false,
        // No CSRF token on an anonymous probe → InvalidCsrfTokenException, which is an
        // AuthenticationException and therefore lands on the firewall entry point. The
        // controller's own „no user → app_login" branch says the same thing.
        \App\Controller\Auth\ResendVerificationEmailController::class => true,
        \App\Controller\Auth\VerifyEmailController::class => false,
        \App\Controller\Auth\VerifyEmailPendingController::class => false,

        // --- Public / marketing / ops -------------------------------------------------
        // `/_design` is ROLE_ADMIN by an in-controller denyAccessUnlessGranted, not by path.
        \App\Controller\DesignStyleguideController::class => true,
        \App\Controller\HealthCheckController::class => false,
        \App\Controller\Public\FaqController::class => false,
        \App\Controller\Public\FeaturesController::class => false,
        \App\Controller\Public\ForBusinessController::class => false,
        \App\Controller\Public\HomeController::class => false,
        \App\Controller\Public\PricingController::class => false,
        \App\Controller\Public\PrivacyController::class => false,
        \App\Controller\Public\CompetitionsListController::class => false,
        // „Žebříček" (item 05) — public on purpose: `/zebricek` serves an anonymous
        // visitor the board of a PUBLIC GLOBAL competition. Which competition may be
        // seen is decided by LeaderboardVoter, not by the route: a private one is not
        // reachable by guessing its UUID (see PublicLeaderboardFlowTest).
        \App\Controller\Public\LeaderboardController::class => false,
        \App\Controller\Webhook\StripeWebhookController::class => false,

        // --- Invitation landings (public on purpose: they onboard logged-out people) ---
        \App\Controller\Invitation\AcceptEmailInvitationController::class => false,
        \App\Controller\Invitation\JoinByShareableLinkController::class => false,
        // B15 made the PIN a landing like the other two, and for the same reason: it is a
        // join secret an organizer hands out, and demanding an account before it may be
        // typed is what lost it. Public here means „may be TYPED", never „may join": with
        // no account the PIN is only remembered, and an unverified account is still sent
        // to the airlock by InvitationAcceptanceService::handleAuthenticated.
        \App\Controller\Invitation\JoinByPinController::class => false,
        \App\Controller\Invitation\QuickJoinByPinController::class => false,
        // The global competition's invitation landing: public like the other three, and
        // the least sensitive of them — its „token" is the competition's own id and the
        // competition is on the public „Soutěže" list anyway. Public still means „may be
        // READ": joining demands a verified account AND the entry fee.
        \App\Controller\Invitation\JoinGlobalCompetitionInviteController::class => false,

        // --- Portal (the whole authenticated app) --------------------------------------
        \App\Controller\Portal\AccountDeleteController::class => true,
        \App\Controller\Portal\DashboardController::class => true,
        \App\Controller\Portal\MatchesController::class => true,
        \App\Controller\Portal\ProfileController::class => true,
        \App\Controller\Portal\Competition\AddAnonymousMemberController::class => true,
        \App\Controller\Portal\Competition\CompetitionDetailController::class => true,
        \App\Controller\Portal\Competition\CompetitionMatchDetailController::class => true,
        \App\Controller\Portal\Competition\CompetitionMatchSelectionController::class => true,
        \App\Controller\Portal\Competition\CompetitionRuleConfigurationController::class => true,
        \App\Controller\Portal\Competition\CompetitionScopeController::class => true,
        \App\Controller\Portal\Competition\CompetitionSettingsController::class => true,
        \App\Controller\Portal\Competition\CreateCompetitionController::class => true,
        \App\Controller\Portal\Competition\DismissBoostIntroController::class => true,
        \App\Controller\Portal\Competition\EnablePremiumController::class => true,
        \App\Controller\Portal\Competition\JoinGlobalCompetitionController::class => true,
        \App\Controller\Portal\Competition\LeaveCompetitionController::class => true,
        \App\Controller\Portal\Competition\LockCompetitionTipsController::class => true,
        \App\Controller\Portal\Competition\ManageMemberTipsController::class => true,
        \App\Controller\Portal\Competition\MyTipsBatchController::class => true,
        \App\Controller\Portal\Competition\PremiumSettingsController::class => true,
        \App\Controller\Portal\Competition\PromoteAnonymousMemberController::class => true,
        \App\Controller\Portal\Competition\PurchaseBoostController::class => true,
        \App\Controller\Portal\Competition\RegeneratePinController::class => true,
        \App\Controller\Portal\Competition\RegenerateShareableLinkController::class => true,
        \App\Controller\Portal\Competition\RemoveMemberController::class => true,
        \App\Controller\Portal\Competition\RevokeInvitationController::class => true,
        \App\Controller\Portal\Competition\RevokePinController::class => true,
        \App\Controller\Portal\Competition\RevokeShareableLinkController::class => true,
        \App\Controller\Portal\Competition\SendBulkInvitationsController::class => true,
        \App\Controller\Portal\Competition\SendInvitationController::class => true,
        \App\Controller\Portal\Competition\SetCompetitionMatchDeadlineController::class => true,
        \App\Controller\Portal\Competition\SoftDeleteCompetitionController::class => true,
        \App\Controller\Portal\Competition\SourceTeamFilterAutocompleteController::class => true,
        \App\Controller\Portal\Competition\SwitchToBoostsController::class => true,
        \App\Controller\Portal\Competition\UnlockCompetitionTipsController::class => true,
        \App\Controller\Portal\Competition\UpdateCompetitionController::class => true,
        \App\Controller\Portal\Credits\BuyCreditsController::class => true,
        \App\Controller\Portal\Credits\CreditsOverviewController::class => true,
        \App\Controller\Portal\Credits\CreditsReturnController::class => true,
        \App\Controller\Portal\Guess\SubmitGuessOnBehalfController::class => true,
        \App\Controller\Portal\Guess\SubmitMemberTipsBatchController::class => true,
        \App\Controller\Portal\Leaderboard\GuessMatrixController::class => true,
        \App\Controller\Portal\Leaderboard\MemberBreakdownController::class => true,
        \App\Controller\Portal\Leaderboard\ResolveTiesController::class => true,
        \App\Controller\Portal\MatchSource\MarkMatchSourceCompletedController::class => true,
        \App\Controller\Portal\MatchSource\MatchSourceDetailController::class => true,
        \App\Controller\Portal\MatchSource\PlayerAutocompleteController::class => true,
        \App\Controller\Portal\MatchSource\ReopenMatchSourceController::class => true,
        \App\Controller\Portal\MatchSource\SoftDeleteMatchSourceController::class => true,
        \App\Controller\Portal\MatchSource\TeamAutocompleteController::class => true,
        \App\Controller\Portal\MatchSource\UpdateMatchSourceController::class => true,
        \App\Controller\Portal\Notifications\MarkAllNotificationsReadController::class => true,
        \App\Controller\Portal\Notifications\NotificationCenterController::class => true,
        \App\Controller\Portal\Notifications\ReadNotificationController::class => true,
        \App\Controller\Portal\SportMatch\BulkImportCommitController::class => true,
        \App\Controller\Portal\SportMatch\BulkImportPreviewController::class => true,
        \App\Controller\Portal\SportMatch\CancelController::class => true,
        \App\Controller\Portal\SportMatch\CreateSportMatchController::class => true,
        \App\Controller\Portal\SportMatch\DownloadTemplateController::class => true,
        \App\Controller\Portal\SportMatch\PostponeController::class => true,
        \App\Controller\Portal\SportMatch\RescheduleController::class => true,
        \App\Controller\Portal\SportMatch\SetFinalScoreController::class => true,
        \App\Controller\Portal\SportMatch\SoftDeleteController::class => true,
        \App\Controller\Portal\SportMatch\SportMatchDetailController::class => true,
        \App\Controller\Portal\SportMatch\UpdateSportMatchController::class => true,
    ];

    /** A syntactically valid UUID v7 — route requirements accept it, no fixture matches it. */
    private const PLACEHOLDER_UUID = '0197b3c4-0000-7000-8000-000000000001';

    /**
     * The map above must describe the router exactly — no missing entries (a new gated area
     * silently escaping the net) and no stale ones (a deleted route leaving a lie behind).
     */
    public function testTheInventoryCoversEveryApplicationRoute(): void
    {
        $actual = [];

        foreach ($this->applicationRoutes() as $route) {
            $actual[$this->controllerOf($route)] = true;
        }

        $expected = array_keys(self::REQUIRES_LOGIN);
        $actual = array_keys($actual);
        sort($expected);
        sort($actual);

        self::assertSame(
            $expected,
            $actual,
            'The anonymous-reachability inventory drifted from the router. Add or remove the controller above.',
        );
    }

    public function testAnonymousVisitorReachesExactlyTheRoutesTheInventoryAllows(): void
    {
        $client = static::createClient();
        $client->catchExceptions(true);

        $misclassified = [];

        foreach ($this->applicationRoutes() as $name => $route) {
            $controller = $this->controllerOf($route);
            $expected = self::REQUIRES_LOGIN[$controller] ?? null;

            if (null === $expected) {
                continue; // reported by testTheInventoryCoversEveryApplicationRoute
            }

            $client->request($this->methodFor($route), $this->urlFor($route));
            $actual = $this->redirectsToLogin($client->getResponse()->getStatusCode(), (string) $client->getResponse()->headers->get('Location'));

            if ($actual !== $expected) {
                $misclassified[] = sprintf(
                    '%s (%s %s): expected %s, got HTTP %d %s',
                    $name,
                    $this->methodFor($route),
                    $this->urlFor($route),
                    $expected ? 'a redirect to the login page' : 'to be served anonymously',
                    $client->getResponse()->getStatusCode(),
                    (string) $client->getResponse()->headers->get('Location'),
                );
            }
        }

        self::assertSame([], $misclassified, "Anonymous reachability changed:\n".implode("\n", $misclassified));
    }

    /**
     * @return array<string, Route>
     */
    private function applicationRoutes(): array
    {
        /** @var RouterInterface $router */
        $router = static::getContainer()->get('router');

        $routes = [];

        foreach ($router->getRouteCollection() as $name => $route) {
            $controller = $route->getDefault('_controller');

            if (!\is_string($controller) || !str_starts_with($controller, 'App\\')) {
                continue;
            }

            $routes[$name] = $route;
        }

        ksort($routes);

        return $routes;
    }

    /**
     * @return class-string
     */
    private function controllerOf(Route $route): string
    {
        /** @var class-string $controller */
        $controller = $route->getDefault('_controller');

        return $controller;
    }

    private function methodFor(Route $route): string
    {
        $methods = $route->getMethods();

        if ([] === $methods || \in_array('GET', $methods, true)) {
            return 'GET';
        }

        return $methods[0];
    }

    private function urlFor(Route $route): string
    {
        return (string) preg_replace_callback(
            '/\{!?(\w+)}/',
            static fn (array $m): string => 'token' === $m[1] ? 'anonymous-probe-token' : self::PLACEHOLDER_UUID,
            $route->getPath(),
        );
    }

    private function redirectsToLogin(int $status, string $location): bool
    {
        if ($status < 300 || $status >= 400) {
            return false;
        }

        return self::LOGIN_PATH === parse_url($location, \PHP_URL_PATH);
    }
}
