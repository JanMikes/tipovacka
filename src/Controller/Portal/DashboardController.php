<?php

declare(strict_types=1);

namespace App\Controller\Portal;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/nastenka', name: 'portal_dashboard')]
final class DashboardController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('portal/dashboard_placeholder.html.twig');
    }
}
