<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/odhlaseni', name: 'app_logout')]
final class LogoutController extends AbstractController
{
    public function __invoke(): Response
    {
        // This method is intentionally empty.
        // Symfony's logout firewall handler intercepts this route and handles logout.
        throw new \LogicException('This method should never be reached.');
    }
}
