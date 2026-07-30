<?php

declare(strict_types=1);

namespace App\Service\Competition;

use App\Command\ForgetPendingJoin\ForgetPendingJoinCommand;
use App\Command\RememberPendingJoin\RememberPendingJoinCommand;
use App\Entity\User;
use App\Enum\InvitationKind;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Remembers which competition somebody is about to join, across everything that can
 * happen in between: signing up, signing in, and — the hard one — walking out to a
 * mailbox to click a verification link (B15).
 *
 * Two layers, on purpose:
 *
 * - **Session**, for a visitor with no account yet. There is nothing to hang the intent
 *   on, and it only has to survive a handful of same-browser requests.
 * - **The `User` row**, the moment an account exists. This is what B15 was missing: the
 *   session lived in a browser-session cookie, while the verification click arrives out
 *   of band — a closed browser, a phone, a webmail in another profile — so the promise
 *   the sign-up page had already made („po registraci se do soutěže připojíš") was lost
 *   whenever the journey crossed browsers. The column survives all of it.
 *
 * Reads prefer the session (it is the fresher of the two — someone can follow a second
 * link before verifying the first) and always clear both.
 */
final readonly class PendingJoinStore
{
    private const string KIND_KEY = 'pending_competition_join_kind';
    private const string TOKEN_KEY = 'pending_competition_join_token';

    public function __construct(
        private RequestStack $requestStack,
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    /**
     * @param User|null $user the account the intent belongs to, when one already exists
     */
    public function remember(PendingJoin $intent, ?User $user = null): void
    {
        $session = $this->requestStack->getSession();
        $session->set(self::KIND_KEY, $intent->kind->value);
        $session->set(self::TOKEN_KEY, $intent->token);

        if (null !== $user) {
            $this->commandBus->dispatch(new RememberPendingJoinCommand(
                userId: $user->id,
                kind: $intent->kind,
                token: $intent->token,
            ));
        }
    }

    /**
     * Reads and clears the intent — from the session and from the account alike, so a
     * failed join never loops back on the next login.
     */
    public function consume(User $user): ?PendingJoin
    {
        $fromSession = $this->consumeSession();

        $fromUser = null !== $user->pendingJoinKind && null !== $user->pendingJoinToken
            ? new PendingJoin($user->pendingJoinKind, $user->pendingJoinToken)
            : null;

        if (null !== $fromUser) {
            $this->commandBus->dispatch(new ForgetPendingJoinCommand(userId: $user->id));
        }

        return $fromSession ?? $fromUser;
    }

    /**
     * The intent of a visitor who has no account yet — read without clearing it, so the
     * „you are about to join X" landing can be re-rendered (or reloaded) as often as the
     * visitor needs before they sign up.
     */
    public function peekAnonymous(): ?PendingJoin
    {
        if (!$this->requestStack->getCurrentRequest()?->hasSession()) {
            return null;
        }

        $session = $this->requestStack->getSession();
        $kind = $session->get(self::KIND_KEY);
        $token = $session->get(self::TOKEN_KEY);

        if (!\is_string($kind) || !\is_string($token) || '' === $token) {
            return null;
        }

        $parsed = InvitationKind::tryFrom($kind);

        return null !== $parsed ? new PendingJoin($parsed, $token) : null;
    }

    /**
     * Drops the intent without acting on it — used when the join just happened inline,
     * so the next login does not replay it and greet the user with „V soutěži již jsi.".
     */
    public function forget(User $user): void
    {
        $this->consumeSession();

        if (null !== $user->pendingJoinKind || null !== $user->pendingJoinToken) {
            $this->commandBus->dispatch(new ForgetPendingJoinCommand(userId: $user->id));
        }
    }

    private function consumeSession(): ?PendingJoin
    {
        $intent = $this->peekAnonymous();

        if ($this->requestStack->getCurrentRequest()?->hasSession()) {
            $session = $this->requestStack->getSession();
            $session->remove(self::KIND_KEY);
            $session->remove(self::TOKEN_KEY);
        }

        return $intent;
    }
}
