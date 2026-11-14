<?php

declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Service\Email\EmailPreviews;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** One sample mail as a bare HTML document — the iframe source of {@see ListEmailPreviewsController}. */
#[Route('/admin/e-maily/{mail}', name: 'admin_email_preview', requirements: ['mail' => '[a-z0-9-]+'], methods: ['GET'])]
final class ShowEmailPreviewController extends AbstractController
{
    public function __construct(
        private readonly EmailPreviews $previews,
    ) {
    }

    public function __invoke(string $mail): Response
    {
        $html = $this->previews->render($mail);

        if (null === $html) {
            throw $this->createNotFoundException();
        }

        return new Response($html);
    }
}
