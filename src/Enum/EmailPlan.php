<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What happens on the e-mail channel for ONE notification — the outcome of
 * {@see \App\Service\Notification\NotificationPolicy}.
 */
enum EmailPlan
{
    /** No mail: the player opted out, has no address, or the type does not mail in their mode. */
    case None;

    /** Mail it right now, on its own. */
    case SendNow;

    /** Leave it on the row for the sweep that sends one mail for many rows. */
    case Queue;
}
