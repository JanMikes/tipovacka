<?php

declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Enum\EmailLogStatus;
use App\Query\ListEmailLog\ListEmailLog;
use App\Query\QueryBus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * „Odeslané e-maily" — the audit log of everything the app tried to send
 * ({@see \App\Entity\EmailLog}): to whom, when, which mail, and whether the
 * relay took it. Admin-only by the `^/admin` access rule; it holds other
 * people's addresses.
 */
#[Route('/admin/odeslane-e-maily', name: 'admin_sent_email_list', methods: ['GET'])]
final class ListSentEmailsController extends AbstractController
{
    public function __construct(
        private readonly QueryBus $queryBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $search = trim($request->query->getString('q'));
        $status = EmailLogStatus::tryFrom($request->query->getString('stav'));

        return $this->render('admin/email/sent_list.html.twig', [
            'log' => $this->queryBus->handle(new ListEmailLog(
                search: '' !== $search ? $search : null,
                status: $status,
                page: max(1, $request->query->getInt('strana', 1)),
            )),
            'search' => $search,
            'status' => $status,
            'statuses' => EmailLogStatus::cases(),
        ]);
    }
}
