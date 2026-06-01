<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Dev/admin-only reference styleguide for DEFERRED (🔮) design-system elements.
 *
 * These elements appear in the Wtips design system but their feature is not built
 * (premium/contribution tiers, scorers editor + „Trefený střelec", notifications
 * bell+feed, Δ rank-change column). They are rendered here as VISUAL-ONLY, INERT
 * references labeled „Připravujeme / reference" — never wired into a production flow.
 *
 * `/_design` is not under an existing `access_control` prefix, so the in-controller
 * `denyAccessUnlessGranted('ROLE_ADMIN')` is the gate: admin → 200, logged-in
 * non-admin → 403, anonymous → redirect to login via the firewall entry point.
 */
#[Route('/_design', name: 'app_design_styleguide', methods: ['GET'])]
final class DesignStyleguideController extends AbstractController
{
    public function __invoke(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('design/styleguide.html.twig');
    }
}
