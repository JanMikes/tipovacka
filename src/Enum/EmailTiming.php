<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How one {@see NotificationType} behaves on the e-mail channel — the type's
 * half of the decision {@see \App\Service\Notification\NotificationPolicy}
 * makes together with the player's {@see EmailFrequency}.
 */
enum EmailTiming
{
    /**
     * Money trouble an organizer must act on today. Sent at once whatever the
     * frequency (short of „žádné e-maily"), and a muted soutěž does not silence it.
     */
    case Urgent;

    /** One event, one mail — or a line in the daily summary. */
    case Standard;

    /**
     * Events that arrive in bursts (a feed evaluating a whole round, an import
     * adding a round of matches). Never mailed one by one: always queued and
     * collapsed into a single mail by the hourly sweep.
     */
    case Burst;

    /**
     * A statement about NOW that goes stale in a queue — the guess reminder.
     * Mailed at once for an „průběžně" player; for a daily one it is not queued
     * at all, the summary recomputes what is missing at the moment it is sent.
     */
    case Live;
}
