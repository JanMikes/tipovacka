<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Command\VerifyUserEmail\VerifyUserEmailCommand;
use App\Exception\UserAlreadyVerified;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use SymfonyCasts\Bundle\VerifyEmail\Exception\ExpiredSignatureException;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

#[Route('/overit-email', name: 'app_verify_email')]
final class VerifyEmailController extends AbstractController
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MessageBusInterface $commandBus,
        private readonly UserRepository $userRepository,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $userId = $request->query->get('id');

        if (null === $userId || !Uuid::isValid($userId)) {
            return $this->render('auth/verify_error.html.twig', [
                'errorMessage' => 'Ověřovací odkaz je neplatný.',
                'showResend' => false,
            ]);
        }

        // The signed URL's HMAC is computed from userId+userEmail, but the URL only
        // carries the id query param — the email is not round-tripped. We must load
        // the user's current email to recompute the expected token.
        $user = $this->userRepository->find(Uuid::fromString($userId));

        if (null === $user || null === $user->email) {
            return $this->render('auth/verify_error.html.twig', [
                'errorMessage' => 'Ověřovací odkaz je neplatný.',
                'showResend' => false,
            ]);
        }

        try {
            $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
                $request,
                $userId,
                $user->email,
            );
        } catch (VerifyEmailExceptionInterface $e) {
            // Log the specific failure type so we can tell URL-mangling (Invalid),
            // expiry, and email-mismatch (Wrong) cases apart in production.
            $this->logger->info('Email verification link rejected', [
                'exception' => $e,
                'userId' => $userId,
                'reason' => $e::class,
            ]);

            $errorMessage = $e instanceof ExpiredSignatureException
                ? 'Tento ověřovací odkaz vypršel. Vyžádejte si prosím nový.'
                : 'Ověřovací odkaz je poškozený nebo neplatný. Vyžádejte si prosím nový.';

            return $this->render('auth/verify_error.html.twig', [
                'errorMessage' => $errorMessage,
                'showResend' => true,
            ]);
        }

        try {
            $this->commandBus->dispatch(new VerifyUserEmailCommand(
                userId: Uuid::fromString($userId),
            ));
        } catch (HandlerFailedException $e) {
            // Already-verified is a terminal state: the signed URL is valid for 7 days,
            // so replaying it must not keep handing out fresh sessions. Redirect to the
            // login form instead of auto-logging in.
            if ($e->getPrevious() instanceof UserAlreadyVerified) {
                $this->addFlash('info', 'Váš e-mail byl již dříve ověřen. Můžete se přihlásit.');

                return $this->redirectToRoute('app_login');
            }

            throw $e;
        }

        // Reload to pick up the verified flag the handler just persisted; the security
        // layer (UserChecker, LoginSubscriber, RequireVerifiedEmailSubscriber on the
        // redirect target) reads `isVerified` and must see the post-commit state.
        $verifiedUser = $this->userRepository->find(Uuid::fromString($userId));

        if (null === $verifiedUser) {
            $this->logger->error('Verified user vanished between command dispatch and login', [
                'userId' => $userId,
            ]);

            $this->addFlash('info', 'E-mail byl ověřen. Přihlas se prosím znovu.');

            return $this->redirectToRoute('app_login');
        }

        // Security::login() may return a response when a LoginSuccessEvent subscriber
        // sets one (e.g. invitation/join intent handling). Honor it so we don't drop
        // the post-login redirect target on the floor.
        $loginResponse = $this->security->login($verifiedUser, firewallName: 'main');
        $this->addFlash('success', 'E-mail byl úspěšně ověřen. Jsi přihlášen(a).');

        return $loginResponse ?? $this->redirectToRoute('portal_dashboard');
    }
}
