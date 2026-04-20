<?php

declare(strict_types=1);

namespace App\Query\GetTournamentDetail;

use App\Query\QueryMessage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryMessage<GetTournamentDetailResult>
 */
final readonly class GetTournamentDetail implements QueryMessage
{
    public function __construct(
        public Uuid $tournamentId,
    ) {
    }
}
