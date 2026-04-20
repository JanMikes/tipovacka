<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Command\ResetUserPassword\ResetUserPasswordCommand;
use App\Entity\User;
use App\Form\ResetPasswordFormData;
use App\Form\ResetPasswordFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-hesla/nove', name: 'app_reset_password_form', methods: ['GET', 'POST'])]
#[Route('/reset-hesla/token/{token}', name: 'app_reset_password')]
final class PasswordResetController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request, string $token = ''): Response
    {
        if ('' !== $token) {
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password_form');
        }

        $token = $this->getTokenFromSession();

        if (null === $token) {
            return $this->render('auth/verify_error.html.twig', [
                'errorMessage' => 'Nebyl nalezen žádný token pro obnovení hesla. Zkuste to znovu.',
                'showResend' => false,
            ]);
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            $this->cleanSessionAfterReset();

            return $this->render('auth/verify_error.html.twig', [
                'errorMessage' => 'Token pro obnovení hesla je neplatný nebo vypršel. Vyžádejte si nový.',
                'showResend' => false,
            ]);
        }

        $formData = new ResetPasswordFormData();
        $form = $this->createForm(ResetPasswordFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->resetPasswordHelper->removeResetRequest($token);

            $this->commandBus->dispatch(new ResetUserPasswordCommand(
                userId: $user->id,
                plainPassword: $formData->newPassword,
            ));

            $this->cleanSessionAfterReset();

            $this->addFlash('success', 'Heslo bylo úspěšně obnoveno. Nyní se můžete přihlásit.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('auth/password_reset.html.twig', [
            'form' => $form,
            'tokenLifetime' => $this->resetPasswordHelper->getTokenLifetime(),
        ]);
    }
}
