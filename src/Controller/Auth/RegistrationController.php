<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/registrace', name: 'app_register', methods: ['GET'])]
final class RegistrationController extends AbstractController
{
    public function __invoke(): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('portal_dashboard');
        }

        return $this->render('auth/register.html.twig');
    }
}
