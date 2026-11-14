<?php

declare(strict_types=1);

namespace App\Value;

use App\Enum\EmailPlan;

/**
 * What one notification does on each channel for one recipient. Produced only
 * by {@see \App\Service\Notification\NotificationPolicy}.
 */
final class NotificationDecision
{
    public function __construct(
        public readonly bool $inApp,
        public readonly EmailPlan $email,
    ) {
    }

    public static function silent(): self
    {
        return new self(false, EmailPlan::None);
    }

    /** Nothing on any channel — no row to write, nothing to dedup. */
    public bool $isSilent {
        get => !$this->inApp && EmailPlan::None === $this->email;
    }
}
