<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/prihlaseni', name: 'app_login')]
final class LoginController extends AbstractController
{
    public function __invoke(AuthenticationUtils $authenticationUtils): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('portal_dashboard');
        }

        return $this->render('auth/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'lastUsername' => $authenticationUtils->getLastUsername(),
        ]);
    }
}
