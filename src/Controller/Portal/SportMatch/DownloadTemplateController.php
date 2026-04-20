<?php

declare(strict_types=1);

namespace App\Controller\Portal\SportMatch;

use App\Service\SportMatch\SportMatchImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/portal/turnaje/zapasy/sablona.csv',
    name: 'portal_sport_match_template_download',
    methods: ['GET'],
)]
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
