<?php

declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Query\GetEmailLogEntry\GetEmailLogEntry;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

/** One logged mail: its outcome, the relay's answer and what the mail said (links redacted). */
#[Route('/admin/odeslane-e-maily/{id}', name: 'admin_sent_email_detail', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
final class ShowSentEmailController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(string $id): Response
    {
        $entry = $this->queryBus->handle(new GetEmailLogEntry(Uuid::fromString($id)));

        if (null === $entry) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/email/sent_detail.html.twig', ['entry' => $entry]);
    }
}
