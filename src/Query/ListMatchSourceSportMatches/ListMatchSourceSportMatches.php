<?php

declare(strict_types=1);

namespace App\Query\ListMatchSourceSportMatches;

use App\Enum\SportMatchState;
use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<SportMatchListItem>>
 */
final readonly class ListMatchSourceSportMatches implements QueryMessage
{
    public function __construct(
        public Uuid $matchSourceId,
        public ?SportMatchState $state = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
    ) {
    }
}
