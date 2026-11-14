<?php

declare(strict_types=1);

namespace App\Query\GetEmailUnsubscribeState;

use App\Enum\EmailFrequency;

final readonly class GetEmailUnsubscribeStateResult
{
    public function __construct(
        /** The address the mails go to, masked — the page is reachable by anyone holding the link. */
        public string $maskedEmail,
        public EmailFrequency $frequency,
        public int $digestHour,
        /** The slug of the thing the link pointed at; null when the page was opened without one (or it no longer exists). */
        public ?string $scopeSlug,
        /** What that thing is called in a sentence. */
        public ?string $scopeLabel,
        /** What switching it off means, in one sentence. */
        public ?string $scopeConsequence,
        /** Whether it is currently ON — i.e. there is still something to unsubscribe from. */
        public bool $scopeSubscribed,
        public bool $scopeIsAll,
    ) {
    }
}
