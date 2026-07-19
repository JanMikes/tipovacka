<?php

declare(strict_types=1);

namespace App\Controller\Portal\Competition;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Thin host for the S08 create-competition wizard Live Component. The whole
 * flow (steps, validation, submit) lives in {@see \App\Twig\Components\Competition\CreateWizard};
 * this controller only renders the shell and forwards the optional `?zdroj={id}`
 * source preselection.
 */
#[Route('/portal/souteze/nova', name: 'portal_competition_create', methods: ['GET'])]
final class CreateCompetitionController extends AbstractController
{
    public function __invoke(Request $request): Response
    {
        $preselectedSourceId = $request->query->get('zdroj');

        return $this->render('portal/competition/create.html.twig', [
            'preselected_source_id' => \is_string($preselectedSourceId) ? $preselectedSourceId : null,
        ]);
    }
}
