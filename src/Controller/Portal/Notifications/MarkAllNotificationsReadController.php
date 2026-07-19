<?php

declare(strict_types=1);

namespace App\Controller\Portal\Notifications;

use App\Command\MarkAllNotificationsRead\MarkAllNotificationsReadCommand;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/portal/oznameni/precteno', name: 'portal_notifications_read_all', methods: ['POST'])]
final class MarkAllNotificationsReadController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('notifications_read_all', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Neplatný bezpečnostní token. Zkuste to znovu.');

            return $this->redirectToRoute('portal_notifications');
        }

        $this->commandBus->dispatch(new MarkAllNotificationsReadCommand($user->id));

        $this->addFlash('success', 'Všechna oznámení označena jako přečtená.');

        return $this->redirectToRoute('portal_notifications');
    }
}
