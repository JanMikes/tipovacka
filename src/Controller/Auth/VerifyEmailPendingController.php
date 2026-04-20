<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/overeni-ceka', name: 'app_verify_email_pending')]
final class VerifyEmailPendingController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('auth/verify_pending.html.twig');
    }
}
