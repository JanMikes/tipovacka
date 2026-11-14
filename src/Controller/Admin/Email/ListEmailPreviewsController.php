<?php

declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Service\Email\EmailPreviews;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * „E-maily" — every mail the app sends, rendered with sample data, side by
 * side at desktop and phone width. The mail itself is served by
 * {@see ShowEmailPreviewController} into an iframe, so it renders in its own
 * document exactly as a mail client would get it.
 */
#[Route('/admin/e-maily', name: 'admin_email_list', methods: ['GET'])]
final class ListEmailPreviewsController extends AbstractController
{
    public function __construct(
        private readonly EmailPreviews $previews,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $previews = $this->previews->list();
        $selected = $request->query->getString('mail');

        if (!isset($previews[$selected])) {
            $selected = (string) array_key_first($previews);
        }

        return $this->render('admin/email/list.html.twig', [
            'previews' => $previews,
            'selected' => $selected,
        ]);
    }
}
