<?php

declare(strict_types=1);

namespace App\Controller\Notification;

use App\Command\UnsubscribeFromEmails\UnsubscribeFromEmailsCommand;
use App\Service\Notification\EmailPreferenceLinks;
use App\Value\UnsubscribeScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The target of the `List-Unsubscribe` header — the „Odhlásit" button the mail
 * client itself draws next to the sender (Gmail, Seznam, Apple Mail). RFC 8058:
 * the client sends a POST with the body `List-Unsubscribe=One-Click`, with no
 * cookies and no login, and expects it to be DONE — no confirmation page, no
 * redirect. So a POST here unsubscribes and answers 200 in plain text.
 *
 * A GET is a person who opened the URL in a browser (some clients offer it as
 * a link): they go to the page that explains and asks, because a GET must
 * never change anything — see {@see EmailPreferencesController}.
 */
#[Route(
    '/e-maily/{token}/odhlasit/{scope}',
    name: 'email_one_click_unsubscribe',
    requirements: ['token' => '[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+'],
    methods: ['GET', 'POST'],
)]
final class OneClickUnsubscribeController extends AbstractController
{
    public function __construct(
        private readonly EmailPreferenceLinks $links,
        #[Autowire(service: 'command.bus')]
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $token, string $scope): Response
    {
        $userId = $this->links->userIdFrom($token);
        $unsubscribeScope = UnsubscribeScope::fromSlug($scope);

        if (null === $userId || null === $unsubscribeScope) {
            throw $this->createNotFoundException();
        }

        if (!$request->isMethod('POST')) {
            return $this->redirectToRoute('email_preferences', ['token' => $token, 'co' => $unsubscribeScope->toSlug()]);
        }

        $this->commandBus->dispatch(new UnsubscribeFromEmailsCommand($userId, $unsubscribeScope));

        return new Response('Odhlášeno.', Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
