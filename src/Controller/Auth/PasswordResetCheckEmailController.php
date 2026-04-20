<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reset-hesla/email-odeslan', name: 'app_check_email')]
final class PasswordResetCheckEmailController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('auth/password_reset_check_email.html.twig');
    }
}
