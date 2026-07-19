<?php

declare(strict_types=1);

namespace App\Twig\Components\Notification;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Nav bell: an unread badge plus a dropdown (latest 5 + „Vše" link). The dropdown
 * polls every 60 s ONLY while open (see the template) so a fresh notification
 * surfaces without a full page load, without hammering the server when closed.
 */
#[AsLiveComponent(name: 'Notification:Bell')]
final class Bell
{
    use DefaultActionTrait;

    private const int DROPDOWN_LIMIT = 5;

    #[LiveProp]
    public bool $open = false;

    public function __construct(
        private readonly Security $security,
        private readonly NotificationRepository $notifications,
    ) {
    }

    public int $unreadCount {
        get {
            $user = $this->currentUser();

            return null !== $user ? $this->notifications->countUnreadForUser($user->id) : 0;
        }
    }

    /** @var list<Notification> */
    public array $latest {
        get {
            $user = $this->currentUser();

            if (null === $user || !$this->open) {
                return [];
            }

            return $this->notifications->listForUser($user->id, self::DROPDOWN_LIMIT);
        }
    }

    #[LiveAction]
    public function toggle(): void
    {
        $this->open = !$this->open;
    }

    private function currentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
