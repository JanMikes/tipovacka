<?php

declare(strict_types=1);

namespace App\Service\Invitation;

use App\Command\AcceptCompetitionInvitation\AcceptCompetitionInvitationCommand;
use App\Command\JoinCompetitionByLink\JoinCompetitionByLinkCommand;
use App\Command\JoinCompetitionByPin\JoinCompetitionByPinCommand;
use App\Command\JoinGlobalCompetition\JoinGlobalCompetitionCommand;
use App\Entity\User;
use App\Enum\InvitationKind;
use App\Exception\AlreadyMember;
use App\Exception\CannotJoinFinishedMatchSource;
use App\Exception\CompetitionInvitationAlreadyAccepted;
use App\Exception\CompetitionInvitationAlreadyRevoked;
use App\Exception\CompetitionInvitationExpired;
use App\Exception\InsufficientCredits;
use App\Query\GetCreditWallet\GetCreditWallet;
use App\Query\QueryBus;
use App\Service\Competition\GlobalJoinReturnIntentSession;
use App\Service\Competition\PendingJoin;
use App\Service\Competition\PendingJoinStore;
use App\Service\Credits\CreditsWord;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Shared post-authentication handling of an invitation context. Both the email and
 * shareable-link controllers, and the unified live component once the user is logged in,
 * funnel through this service to dispatch the right join command and route the response.
 */
final readonly class InvitationAcceptanceService
{
    public function __construct(
        private InvitationContextResolver $contextResolver,
        private UrlGeneratorInterface $urlGenerator,
        private RequestStack $requestStack,
        private ClockInterface $clock,
        private PendingJoinStore $pendingJoins,
        private GlobalJoinReturnIntentSession $returnIntent,
        private QueryBus $queryBus,
        private Environment $twig,
        #[Autowire(service: 'command.bus')]
        private MessageBusInterface $commandBus,
    ) {
    }

    /**
     * Routes an authenticated user landing on an invitation URL: verification gate,
     * email-mismatch detection (email-kind only), or immediate join.
     */
    public function handleAuthenticated(InvitationContext $context, User $currentUser): Response
    {
        if (InvitationKind::Email === $context->kind
            && null !== $context->presetEmail
            && (null === $currentUser->email || 0 !== strcasecmp($currentUser->email, $context->presetEmail))
        ) {
            return new Response($this->twig->render('invitation/landing.html.twig', [
                'step' => 'email_mismatch',
                'context' => $context,
                'current_user_email' => $currentUser->email,
            ]));
        }

        // Email-kind invitations targeted at the user's own address verify the account
        // implicitly when accepted (see AcceptCompetitionInvitationHandler), so don't block
        // unverified users at the gate. A shareable link or a PIN carries no such proof.
        if (!$currentUser->isVerified && InvitationKind::Email !== $context->kind) {
            $this->rememberIntent($context, $currentUser);
            $this->flash('warning', sprintf(
                'Nejprve si ověř svou e-mailovou adresu — pak tě rovnou přidáme do soutěže %s.',
                $context->competitionName,
            ));

            return new RedirectResponse($this->urlGenerator->generate('app_verify_email_pending'));
        }

        return $this->joinCompetitionAsUser($context, $currentUser);
    }

    /**
     * Dispatches the join command appropriate for the kind, mapping known business
     * exceptions into flash + appropriate redirects.
     */
    public function joinCompetitionAsUser(InvitationContext $context, User $user): Response
    {
        // The join is happening right now, so any intent recorded for later is spent —
        // leaving it would replay at the next login as „V soutěži již jsi.".
        $this->pendingJoins->forget($user);

        try {
            $command = match ($context->kind) {
                InvitationKind::Email => new AcceptCompetitionInvitationCommand(userId: $user->id, token: $context->token),
                InvitationKind::ShareableLink => new JoinCompetitionByLinkCommand(userId: $user->id, token: $context->token),
                InvitationKind::Pin => new JoinCompetitionByPinCommand(userId: $user->id, pin: $context->token),
                // A global competition is joined the one and only way it is ever joined —
                // by paying. Arriving through an invitation link buys no discount and no
                // shortcut; it merely saved the invitee from having to find the page.
                InvitationKind::GlobalCompetition => new JoinGlobalCompetitionCommand(
                    userId: $user->id,
                    competitionId: $context->competitionId,
                ),
            };

            $this->commandBus->dispatch($command);

            $this->flash('success', 'Byl(a) jsi přidán(a) do soutěže.');
        } catch (HandlerFailedException $handlerFailed) {
            $inner = $handlerFailed->getPrevious();

            if ($inner instanceof AlreadyMember) {
                $this->flash('info', 'V soutěži již jsi.');
            } elseif ($inner instanceof InsufficientCredits) {
                return $this->redirectToTopUp($context, $user);
            } elseif ($inner instanceof CannotJoinFinishedMatchSource) {
                $this->flash('warning', 'Zdroj zápasů této soutěže je již ukončen.');

                return new RedirectResponse($this->urlGenerator->generate('dashboard'));
            } elseif ($inner instanceof CompetitionInvitationExpired
                || $inner instanceof CompetitionInvitationAlreadyAccepted
                || $inner instanceof CompetitionInvitationAlreadyRevoked
            ) {
                return $this->renderStatus($this->refreshContext($context));
            } else {
                throw $handlerFailed;
            }
        }

        // `?pripojeno=1` (item 28) states the fact „you have just joined": the
        // competition page uses it to withhold the boost-price modal this once,
        // so the welcome and the upsell do not arrive in the same breath.
        return new RedirectResponse($this->urlGenerator->generate(
            'competition_detail',
            ['id' => $context->competitionId->toRfc4122(), 'pripojeno' => 1],
        ));
    }

    /**
     * The invitee said yes to a paid competition with too thin a wallet. Send them to the
     * top-up with the shortfall named, remembering the competition so the credits page can
     * offer the way back — the same treatment „Připojit se" gives on the competition page
     * ({@see \App\Controller\Portal\Competition\JoinGlobalCompetitionController}). We never
     * auto-join after a top-up; they click again.
     */
    private function redirectToTopUp(InvitationContext $context, User $user): RedirectResponse
    {
        $balance = $this->queryBus->handle(new GetCreditWallet($user->id))->balance;

        $this->returnIntent->store($context->competitionId->toRfc4122());
        $this->flash('warning', sprintf(
            'Na vstupné do soutěže %s potřebujete ještě %s.',
            $context->competitionName,
            CreditsWord::format(max(0, $context->entryFeeCredits - $balance)),
        ));

        return new RedirectResponse($this->urlGenerator->generate('credits'));
    }

    /**
     * @param User|null $user the account the intent belongs to, when one already exists —
     *                        passing it makes the intent survive the verification mail
     *                        round trip, which the session alone cannot (B15)
     */
    public function rememberIntent(InvitationContext $context, ?User $user = null): void
    {
        $this->pendingJoins->remember(new PendingJoin($context->kind, $context->token), $user);
    }

    public function flash(string $type, string $message): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->hasSession()) {
            return;
        }

        $request->getSession()->getFlashBag()->add($type, $message);
    }

    public function refreshContext(InvitationContext $context): InvitationContext
    {
        return $this->contextResolver->resolve(
            $context->kind,
            $context->token,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }

    public function renderStatus(InvitationContext $context): Response
    {
        return new Response($this->twig->render('invitation/landing.html.twig', [
            'step' => $context->status->value,
            'context' => $context,
        ]));
    }
}
