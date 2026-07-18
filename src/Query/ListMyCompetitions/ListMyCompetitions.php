<?php

declare(strict_types=1);

namespace App\Query\ListMyCompetitions;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<CompetitionListItem>>
 */
final readonly class ListMyCompetitions implements QueryMessage
{
    public function __construct(
        public Uuid $userId,
    ) {
    }
}
