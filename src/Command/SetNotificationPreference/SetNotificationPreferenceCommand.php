<?php

declare(strict_types=1);

namespace App\Command\SetNotificationPreference;

use App\Enum\NotificationType;
use Symfony\Component\Uid\Uuid;

final readonly class SetNotificationPreferenceCommand
{
    public function __construct(
        public Uuid $userId,
        public NotificationType $type,
        public bool $inApp,
        public bool $email,
    ) {
    }
}
