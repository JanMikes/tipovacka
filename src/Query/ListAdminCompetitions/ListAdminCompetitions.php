<?php

declare(strict_types=1);

namespace App\Query\ListAdminCompetitions;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<AdminCompetitionItem>>
 */
final readonly class ListAdminCompetitions implements QueryMessage
{
    public function __construct(
        public ?Uuid $matchSourceId = null,
    ) {
    }
}
