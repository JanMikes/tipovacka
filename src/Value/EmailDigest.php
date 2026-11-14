<?php

declare(strict_types=1);

namespace App\Value;

/**
 * Everything ONE digest mail says — many notifications of one player folded
 * into a single mail, grouped by soutěž. Composed only by
 * {@see \App\Service\Notification\EmailDigestComposer}.
 */
final class EmailDigest
{
    /**
     * @param list<EmailDigestSection> $sections
     */
    public function __construct(
        /** The most actionable thing inside — the mail's heading, e.g. „Chybí vám 3 tipy". */
        public readonly string $headline,
        /** Whatever else is inside, as one line under the heading; '' when the headline says it all. */
        public readonly string $summary,
        public readonly array $sections,
        public readonly string $actionLabel,
        /** Host-relative path the button leads to. */
        public readonly string $actionUrl,
        /** The player's once-a-day summary, as opposed to the hourly collapse of a burst. */
        public readonly bool $daily,
        /** What the mail client's own „unsubscribe" button switches off. */
        public readonly UnsubscribeScope $unsubscribeScope,
    ) {
    }

    /** The subject line: everything the mail holds, most actionable first. */
    public string $subject {
        get => '' === $this->summary ? $this->headline : sprintf('%s · %s', $this->headline, mb_strtolower(mb_substr($this->summary, 0, 1)).mb_substr($this->summary, 1));
    }
}
