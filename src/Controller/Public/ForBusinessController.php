<?php

declare(strict_types=1);

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/pro-firmy', name: 'app_for_business', methods: ['GET'])]
final class ForBusinessController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('public/for_business.html.twig');
    }
}
