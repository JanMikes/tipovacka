<?php

declare(strict_types=1);

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/funkce', name: 'app_features', methods: ['GET'])]
final class FeaturesController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('public/features.html.twig');
    }
}
