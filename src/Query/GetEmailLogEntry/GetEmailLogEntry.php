<?php

declare(strict_types=1);

namespace App\Query\GetEmailLogEntry;

use App\Query\ListEmailLog\EmailLogItem;
use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<EmailLogItem|null>
 */
final readonly class GetEmailLogEntry implements QueryMessage
{
    public function __construct(
        public Uuid $id,
    ) {
    }
}
