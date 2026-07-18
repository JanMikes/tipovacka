<?php

declare(strict_types=1);

namespace App\Query\ListDiscoverablePublicMatchSources;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<DiscoverableMatchSourceItem>>
 */
final readonly class ListDiscoverablePublicMatchSources implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
