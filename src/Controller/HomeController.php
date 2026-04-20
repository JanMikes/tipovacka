<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'app_home')]
final class HomeController extends AbstractController
{
    public function __invoke(): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('portal_dashboard');
        }

        return $this->redirectToRoute('app_login');
    }
}
