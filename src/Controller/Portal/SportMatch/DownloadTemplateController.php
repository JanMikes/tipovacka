<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Service\SportMatch\SportMatchImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(
    '/turnaje/zapasy/sablona.csv',
    name: 'sport_match_template_download',
    methods: ['GET'],
)]
#[IsGranted('ROLE_USER')]
final class DownloadTemplateController extends AbstractController
{
    public function __construct(
        private readonly SportMatchImporter $importer,
    ) {
    }

    public function __invoke(): Response
    {
        $csv = $this->importer->generateTemplateCsv();

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="zapasy-sablona.csv"');

        return $response;
    }
}
