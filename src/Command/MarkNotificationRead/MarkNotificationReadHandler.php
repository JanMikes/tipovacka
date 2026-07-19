<?php

declare(strict_types=1);

namespace App\Command\MarkNotificationRead;

use App\Repository\NotificationRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MarkNotificationReadHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(MarkNotificationReadCommand $command): void
    {
        // Scoped to the owner — a user can only ever mark their own rows read.
        $notification = $this->notificationRepository->findForUser($command->notificationId, $command->userId);

        $notification?->markRead(\DateTimeImmutable::createFromInterface($this->clock->now()));
    }
}
