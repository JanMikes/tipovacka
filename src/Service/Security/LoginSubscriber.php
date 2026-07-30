<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Command\AcceptCompetitionInvitation\AcceptCompetitionInvitationCommand;
use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\Command\JoinCompetitionByPin\JoinCompetitionByPinCommand;
use App\Entity\Competition;
use App\Entity\CompetitionInvitation;
use App\Entity\User;
use App\Enum\InvitationKind;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedMatchSource;
use App\Exception\CompetitionInvitationAlreadyAccepted;
use App\Exception\CompetitionInvitationAlreadyRevoked;
use App\Exception\CompetitionInvitationExpired;
use App\Exception\InvalidInvitationToken;
use App\Exception\InvalidPin;
use App\Exception\InvalidShareableLink;
use App\Service\Competition\PendingJoin;
use App\Service\Competition\PendingJoinStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Uid\Uuid;

/**
 * Completes a pending competition join at the first moment the account is allowed to
 * have one — which, for a shareable link or a PIN, is the login that follows the e-mail
 * verification.
 *
 * B15: the user is then sent to the **competition**, not to the Nástěnka. Landing on an
 * empty dashboard after being promised a join reads as „it did not work", which is
 * exactly how the bug was reported even on the runs where the join had succeeded.
 */
final class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly PendingJoinStore $pendingJoins,
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
            // Sign-up lands straight on `/overeni-ceka`, a full page that already explains
            // the next step — a flash on top of it is noise (W5). Only warn when the user
            // is re-logging in to an account that is still unverified.
            if (!$this->isRegistrationRequest($request)) {
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

        $pending = $this->pendingJoins->consume($user);

        if (null === $pending) {
            return;
        }

        $competitionId = $this->join($pending, $user, $flashBag);

        $event->setResponse(new RedirectResponse(
            null !== $competitionId
                ? $this->urlGenerator->generate('competition_detail', ['id' => $competitionId->toRfc4122()])
                : $this->urlGenerator->generate('dashboard')
        ));
    }

    /**
     * @return Uuid|null the competition joined, or null when the intent could not be honoured
     */
    private function join(PendingJoin $pending, User $user, ?FlashBagInterface $flashBag): ?Uuid
    {
        $command = match ($pending->kind) {
            InvitationKind::Email => new AcceptCompetitionInvitationCommand(userId: $user->id, token: $pending->token),
            InvitationKind::ShareableLink => new JoinCompetitionByLinkCommand(userId: $user->id, token: $pending->token),
            InvitationKind::Pin => new JoinCompetitionByPinCommand(userId: $user->id, pin: $pending->token),
        };

        try {
            $envelope = $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $handlerFailed) {
            $this->explain($handlerFailed->getPrevious(), $flashBag);

            return null;
        } catch (\Throwable $e) {
            $this->explain($e, $flashBag);

            return null;
        }

        $flashBag?->add('success', 'Byl(a) jsi přidán(a) do soutěže.');

        return $this->competitionIdOf($envelope->last(HandledStamp::class)?->getResult());
    }

    /**
     * Only the known „this invitation cannot be honoured" outcomes are turned into a
     * message; anything else is a real fault and must keep bubbling up.
     */
    private function explain(?\Throwable $failure, ?FlashBagInterface $flashBag): void
    {
        if ($failure instanceof AlreadyMember) {
            $flashBag?->add('info', 'V soutěži již jsi.');

            return;
        }

        $known = $failure instanceof InvalidShareableLink
            || $failure instanceof InvalidPin
            || $failure instanceof InvalidInvitationToken
            || $failure instanceof CannotJoinFinishedMatchSource
            || $failure instanceof CompetitionInvitationExpired
            || $failure instanceof CompetitionInvitationAlreadyAccepted
            || $failure instanceof CompetitionInvitationAlreadyRevoked;

        if (!$known) {
            throw $failure ?? new \LogicException('Pending join failed without a cause.');
        }

        $flashBag?->add('warning', 'Pozvánku do soutěže se nepodařilo uplatnit.');
    }

    private function competitionIdOf(mixed $result): ?Uuid
    {
        return match (true) {
            $result instanceof Competition => $result->id,
            $result instanceof CompetitionInvitation => $result->competition->id,
            default => null,
        };
    }

    /**
     * Sign-up runs through the `Auth:RegistrationForm` Live Component, so the request route
     * is the shared `ux_live_component`, not `app_register` — both spellings count, and so
     * does the invitation landing's own form.
     */
    private function isRegistrationRequest(?Request $request): bool
    {
        if (null === $request) {
            return false;
        }

        $component = $request->attributes->get('_live_component');

        return 'app_register' === $request->attributes->get('_route')
            || 'Auth:RegistrationForm' === $component
            || 'Auth:InvitationForm' === $component;
    }
}
