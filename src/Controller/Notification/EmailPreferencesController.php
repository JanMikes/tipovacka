<?php

declare(strict_types=1);

namespace App\Controller\Notification;

use App\Command\ChangeEmailDelivery\ChangeEmailDeliveryCommand;
use App\Command\UnsubscribeFromEmails\UnsubscribeFromEmailsCommand;
use App\Enum\EmailFrequency;
use App\Query\GetEmailUnsubscribeState\GetEmailUnsubscribeState;
use App\Query\QueryBus;
use App\Service\Notification\EmailPreferenceLinks;
use App\Value\UnsubscribeScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * „Odhlásit" from a notification mail — a page that works WITHOUT logging in.
 * The signed token in the URL says which player the mail was sent to
 * ({@see EmailPreferenceLinks}); `?co=` says what the link was about.
 *
 * Deliberately public: an unsubscribe behind a login is an unsubscribe most
 * people answer with the spam button. The token is therefore NOT a session —
 * everything this page can do only ever turns e-mail DOWN (switch a type off,
 * mute a soutěž, go daily, stop all), plus the undo of what was just done.
 * Turning things on beyond that needs the real settings page and a login.
 *
 * A GET never changes anything: mail scanners and link previews follow GET
 * links, and they must not unsubscribe anyone. The page shows one button; the
 * POST does it. (The mail client's own one-click button is the other
 * controller, {@see OneClickUnsubscribeController}.)
 *
 * No CSRF token on the form, on purpose: the page has to work in a browser
 * that has never seen our cookies, and the signed link is the credential.
 */
#[Route(
    '/e-maily/{token}',
    name: 'email_preferences',
    requirements: ['token' => '[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+'],
    methods: ['GET', 'POST'],
)]
final class EmailPreferencesController extends AbstractController
{
    public function __construct(
        private readonly EmailPreferenceLinks $links,
        private readonly QueryBus $queryBus,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $token): Response
    {
        $userId = $this->links->userIdFrom($token);

        if (null === $userId) {
            throw $this->createNotFoundException();
        }

        $scope = UnsubscribeScope::fromSlug($request->isMethod('POST')
            ? $request->request->getString('co')
            : $request->query->getString('co'));

        $state = $this->queryBus->handle(new GetEmailUnsubscribeState($userId, $scope));

        if (null === $state) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $action = $request->request->getString('akce');

            if ('denne' === $action) {
                // Only ever a step DOWN: from „průběžně" to one summary a day. For a
                // player on „žádné e-maily" this would switch mail back ON.
                if (EmailFrequency::Immediate === $state->frequency) {
                    $this->commandBus->dispatch(new ChangeEmailDeliveryCommand($userId, EmailFrequency::Daily));
                }
            } elseif (null !== $scope && in_array($action, ['odhlasit', 'obnovit'], true)) {
                $this->commandBus->dispatch(new UnsubscribeFromEmailsCommand(
                    userId: $userId,
                    scope: $scope,
                    resubscribe: 'obnovit' === $action,
                ));
            }

            return $this->redirectToRoute('email_preferences', array_filter([
                'token' => $token,
                'co' => $scope?->toSlug(),
                'stav' => in_array($action, ['odhlasit', 'obnovit', 'denne'], true) ? $action : null,
            ]));
        }

        return $this->render('notification/email_preferences.html.twig', [
            'token' => $token,
            'state' => $state,
            'done' => $request->query->getString('stav'),
        ]);
    }
}
