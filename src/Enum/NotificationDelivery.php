<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * A per-call restriction on WHICH channels one delivery may use. It never turns
 * a channel on — the user's {@see \App\Entity\NotificationPreference} (or the
 * type's default) still decides that; this can only narrow the result.
 *
 * It exists because one notification type can legitimately want different
 * granularity per channel. `match_evaluated` is the case that forced it: an
 * in-app row per match is useful (that is the feed), but one e-mail per match
 * is a burst — a Saturday round finishing eight zápasů at once would send eight
 * mails, something manual result entry never produced because a human enters
 * results one at a time. So the per-match notification is InAppOnly and
 * {@see \App\Command\SendMatchEvaluationDigests\SendMatchEvaluationDigestsHandler}
 * sends one EmailOnly digest, exactly as the guess reminder does.
 */
enum NotificationDelivery
{
    /** Honor both channel preferences — the ordinary case. */
    case Default;

    /** Write the in-app row, never mail. */
    case InAppOnly;

    /** Mail only; the row is written invisible, purely as the dedup marker. */
    case EmailOnly;
}
