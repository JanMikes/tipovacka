<?php

declare(strict_types=1);

namespace App\Query\ListEmailLog;

final class ListEmailLogResult
{
    /**
     * @param list<EmailLogItem> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public bool $hasNextPage {
        get => $this->page * $this->perPage < $this->total;
    }
}
