<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Command\VerifyUserEmail\VerifyUserEmailCommand;
use App\Exception\UserAlreadyVerified;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

#[Route('/overit-email', name: 'app_verify_email')]
final class VerifyEmailController extends AbstractController
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MessageBusInterface $commandBus,
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

        try {
            $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
                $request,
                $userId,
                (string) $request->query->get('email', ''),
            );
        } catch (VerifyEmailExceptionInterface) {
            return $this->render('auth/verify_error.html.twig', [
                'errorMessage' => 'Ověřovací odkaz je neplatný nebo vypršel. Vyžádejte si nový.',
                'showResend' => true,
            ]);
        }

        try {
            $this->commandBus->dispatch(new VerifyUserEmailCommand(
                userId: Uuid::fromString($userId),
            ));
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof UserAlreadyVerified) {
                $this->addFlash('info', 'Váš e-mail byl již dříve ověřen. Můžete se přihlásit.');

                return $this->redirectToRoute('app_login');
            }

            throw $e;
        }

        $this->addFlash('success', 'E-mail byl úspěšně ověřen! Nyní se můžete přihlásit.');

        return $this->redirectToRoute('app_login');
    }
}
