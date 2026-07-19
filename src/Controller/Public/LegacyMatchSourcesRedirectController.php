<?php

declare(strict_types=1);

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Permanent redirect from the retired `/turnaje` discovery URL to `/souteze`
 * (global-competition discovery). Kept cheap so old links / bookmarks survive.
 */
#[Route('/turnaje', name: 'public_match_sources_list_legacy', methods: ['GET'])]
final class LegacyMatchSourcesRedirectController extends AbstractController
{
    public function __invoke(): RedirectResponse
    {
        return $this->redirectToRoute('public_competitions_list', [], Response::HTTP_MOVED_PERMANENTLY);
    }
}
