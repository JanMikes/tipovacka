<?php

declare(strict_types=1);

namespace App\Query\ListMyOwnedMatchSources;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<MatchSourceListItem>>
 */
final readonly class ListMyOwnedMatchSources implements QueryMessage
{
    public function __construct(
        public Uuid $ownerId,
    ) {
    }
}
