<?php

declare(strict_types=1);

namespace App\Value;

/**
 * One row of a list inside a notification mail — a soutěž with its missing
 * tips, an evaluated match, a piece of news in the daily summary.
 */
final readonly class EmailItem
{
    public function __construct(
        public string $title,
        public string $detail = '',
        /** Host-relative path; the mail template makes it absolute. */
        public ?string $url = null,
    ) {
    }
}
