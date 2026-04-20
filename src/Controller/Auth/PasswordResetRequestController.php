<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Command\RequestPasswordReset\RequestPasswordResetCommand;
use App\Form\RequestPasswordResetFormData;
use App\Form\RequestPasswordResetFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reset-hesla', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
final class PasswordResetRequestController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $formData = new RequestPasswordResetFormData();
        $form = $this->createForm(RequestPasswordResetFormType::class, $formData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->commandBus->dispatch(new RequestPasswordResetCommand(
                email: $formData->email,
            ));

            // Always redirect — no enumeration
            return $this->redirectToRoute('app_check_email');
        }

        return $this->render('auth/password_reset_request.html.twig', [
            'form' => $form,
        ]);
    }
}
