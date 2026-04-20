<?php

declare(strict_types=1);

namespace App\Query\ListTournamentSportMatches;

use App\Enum\SportMatchState;
use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<list<SportMatchListItem>>
 */
final readonly class ListTournamentSportMatches implements QueryMessage
{
    public function __construct(
        public Uuid $tournamentId,
        public ?SportMatchState $state = null,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
    ) {
    }
}
