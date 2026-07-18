<?php

declare(strict_types=1);

namespace App\Query\ListCompetitionsForMatchSource;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<CompetitionMatchSourceListItem>>
 */
final readonly class ListCompetitionsForMatchSource implements QueryMessage
{
    public function __construct(
        public Uuid $matchSourceId,
    ) {
    }
}
