<?php

declare(strict_types=1);

namespace App\Command\MarkNotificationRead;

use Symfony\Component\Uid\Uuid;

final readonly class MarkNotificationReadCommand
{
    public function __construct(
        public Uuid $userId,
        public Uuid $notificationId,
    ) {
    }
}
