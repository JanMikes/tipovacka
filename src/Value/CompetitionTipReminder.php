<?php

declare(strict_types=1);

namespace App\Value;

use App\Entity\Competition;

/**
 * The tips ONE player still owes in ONE soutěž inside a reminder window —
 * a line of the guess reminder and of the daily summary. Produced only by
 * {@see \App\Service\Notification\TipReminderFinder}.
 */
final readonly class CompetitionTipReminder
{
    /**
     * @param list<string> $bucketKeys one dedup key per Prague deadline-day covered, see the finder
     */
    public function __construct(
        public Competition $competition,
        public int $count,
        public \DateTimeImmutable $earliestDeadline,
        public array $bucketKeys,
    ) {
    }
}
