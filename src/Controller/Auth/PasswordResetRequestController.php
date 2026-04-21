<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reset-hesla', name: 'app_forgot_password_request', methods: ['GET'])]
final class PasswordResetRequestController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('auth/password_reset_request.html.twig');
    }
}
