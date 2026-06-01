<?php

declare(strict_types=1);

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cenik', name: 'app_pricing', methods: ['GET'])]
final class PricingController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('public/pricing.html.twig');
    }
}
