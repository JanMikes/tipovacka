<?php

declare(strict_types=1);

namespace App\Controller\Portal\Notifications;

use App\Command\MarkNotificationRead\MarkNotificationReadCommand;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Uid\Uuid;

/**
 * Click-through: marks a single notification read and forwards to its target
 * url (the feed and the bell dropdown both link here). Scoped to the owner —
 * another user's id simply falls through to the center.
 */
#[Route(
    '/portal/oznameni/{id}/precteno',
    name: 'portal_notification_read',
    requirements: ['id' => Requirement::UUID],
    methods: ['GET'],
)]
final class ReadNotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $notification = $this->notificationRepository->findForUser(Uuid::fromString($id), $user->id);

        if (null === $notification) {
            return $this->redirectToRoute('portal_notifications');
        }

        $this->commandBus->dispatch(new MarkNotificationReadCommand(
            userId: $user->id,
            notificationId: $notification->id,
        ));

        if (null !== $notification->url && '' !== $notification->url) {
            return $this->redirect($notification->url);
        }

        return $this->redirectToRoute('portal_notifications');
    }
}
