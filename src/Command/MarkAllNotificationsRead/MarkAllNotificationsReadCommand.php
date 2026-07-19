<?php

declare(strict_types=1);

namespace App\Command\MarkAllNotificationsRead;

use Symfony\Component\Uid\Uuid;

final readonly class MarkAllNotificationsReadCommand
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
