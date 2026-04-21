<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-hesla/nove', name: 'app_reset_password_form', methods: ['GET'])]
#[Route('/reset-hesla/token/{token}', name: 'app_reset_password', methods: ['GET'])]
final class PasswordResetController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
    ) {
    }

    public function __invoke(string $token = ''): Response
    {
        if ('' !== $token) {
            $this->storeTokenInSession($token);

            return new RedirectResponse($this->generateUrl('app_reset_password_form'));
        }

        $sessionToken = $this->getTokenFromSession();

        if (null === $sessionToken) {
            return $this->render('auth/verify_error.html.twig', [
                'errorMessage' => 'Nebyl nalezen žádný token pro obnovení hesla. Zkuste to znovu.',
                'showResend' => false,
            ]);
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($sessionToken);
        } catch (ResetPasswordExceptionInterface) {
            $this->cleanSessionAfterReset();

            return $this->render('auth/verify_error.html.twig', [
                'errorMessage' => 'Token pro obnovení hesla je neplatný nebo vypršel. Vyžádejte si nový.',
                'showResend' => false,
            ]);
        }

        return $this->render('auth/password_reset.html.twig', [
            'tokenLifetime' => $this->resetPasswordHelper->getTokenLifetime(),
        ]);
    }
}
