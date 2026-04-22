<?php

declare(strict_types=1);

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ochrana-soukromi', name: 'app_privacy', methods: ['GET'])]
final class PrivacyController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('public/privacy.html.twig');
    }
}
