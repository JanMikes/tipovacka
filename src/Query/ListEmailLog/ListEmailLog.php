<?php

declare(strict_types=1);

namespace App\Query\ListEmailLog;

use App\Enum\EmailLogStatus;
use App\Query\QueryMessage;

/**
 * The admin „Odeslané e-maily" page: logged mail attempts, newest first,
 * optionally narrowed to an address / subject fragment and to one outcome.
 *
 * @implements QueryMessage<ListEmailLogResult>
 */
final readonly class ListEmailLog implements QueryMessage
{
    public const int PER_PAGE = 50;

    public function __construct(
        public ?string $search = null,
        public ?EmailLogStatus $status = null,
        public int $page = 1,
    ) {
    }
}
