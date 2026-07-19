<?php

declare(strict_types=1);

namespace App\Controller\Portal\Notifications;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The notification center: a paginated feed (unread highlighted) plus a
 * „Nastavení oznámení" tab (the {@see \App\Twig\Components\Notification\Preferences}
 * live matrix). Tab is chosen by the `tab` query param — no JS needed.
 */
#[Route('/portal/oznameni', name: 'portal_notifications', methods: ['GET'])]
final class NotificationCenterController extends AbstractController
{
    private const int PER_PAGE = 20;

    public function __construct(
        private readonly NotificationRepository $notificationRepository,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $tab = 'nastaveni' === $request->query->get('tab') ? 'nastaveni' : 'feed';
        $page = max(1, $request->query->getInt('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $total = $this->notificationRepository->countForUser($user->id);
        $notifications = 'feed' === $tab
            ? $this->notificationRepository->listForUser($user->id, self::PER_PAGE, $offset)
            : [];

        return $this->render('portal/notifications/center.html.twig', [
            'tab' => $tab,
            'notifications' => $notifications,
            'unreadCount' => $this->notificationRepository->countUnreadForUser($user->id),
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => $total,
            'hasNextPage' => $offset + self::PER_PAGE < $total,
        ]);
    }
}
