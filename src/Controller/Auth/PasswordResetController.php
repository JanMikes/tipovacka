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
            return $this->renderLinkError('Odkaz pro obnovení hesla jsme v tomto prohlížeči nenašli. Otevřete ho znovu z e-mailu, nebo si vyžádejte nový.');
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($sessionToken);
        } catch (ResetPasswordExceptionInterface) {
            $this->cleanSessionAfterReset();

            return $this->renderLinkError('Odkaz pro obnovení hesla je neplatný nebo vypršel — platí 1 hodinu a použít jde jen jednou. Vyžádejte si nový, pošleme vám ho hned.');
        }

        return $this->render('auth/password_reset.html.twig', [
            'tokenLifetime' => $this->resetPasswordHelper->getTokenLifetime(),
        ]);
    }

    private function renderLinkError(string $message): Response
    {
        return $this->render('auth/verify_error.html.twig', [
            'errorMessage' => $message,
            'retryUrl' => $this->generateUrl('app_forgot_password_request'),
            'retryLabel' => 'Poslat nový odkaz pro obnovení hesla',
        ]);
    }
}
