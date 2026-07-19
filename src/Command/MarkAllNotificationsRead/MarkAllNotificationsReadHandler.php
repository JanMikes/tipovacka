<?php

declare(strict_types=1);

namespace App\Command\MarkAllNotificationsRead;

use App\Repository\NotificationRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MarkAllNotificationsReadHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(MarkAllNotificationsReadCommand $command): void
    {
        $this->notificationRepository->markAllRead(
            $command->userId,
            \DateTimeImmutable::createFromInterface($this->clock->now()),
        );
    }
}
